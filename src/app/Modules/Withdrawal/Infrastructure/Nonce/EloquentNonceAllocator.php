<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Nonce;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Withdrawal\Domain\Contract\NonceAllocator;
use App\Modules\Withdrawal\Domain\Contract\NonceProbeRegistry;
use App\Modules\Withdrawal\Domain\Exception\NonceAllocationFailedException;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models\NonceAssignmentModel;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Сериализует выдачу EVM-nonce для каждой пары (chain, hot) через PostgreSQL
 * advisory lock и UNIQUE-constraint в таблице `nonce_assignments`.
 *
 * Что такое nonce и зачем он нужен — см. GUIDE.md, Урок 3 («Аккаунт-модель»):
 * EVM-сеть строго проверяет, что транзакция с `nonce=N` принимается только
 * если от данного `from` уже подтверждено ровно `N` транзакций. Пропуск
 * номера → транзакция вечно «висит» в pending.
 *
 * Алгоритм (GUIDE.md, Урок 3, раздел «В коде»):
 *   1. BEGIN
 *   2. pg_advisory_xact_lock(crc32(chain_id + ':' + hot_address))
 *      — сериализуем выделение для пары (сеть, горячий адрес) на уровне Postgres.
 *   3. SELECT MAX(nonce) WHERE chain_id=? AND hot_address=?
 *   4. Если MAX == NULL → зондируем ноду: eth_getTransactionCount(addr, "latest")
 *      (см. {@see NonceProbeRegistry}/{@see EvmNonceProbe}).
 *   5. next = MAX + 1, либо зондированное значение.
 *   6. INSERT row(chain_id, hot_address, next, allocated_at=now)
 *   7. COMMIT (advisory lock освобождается автоматически по концу tx).
 *
 * ⚠️ Конфликт UNIQUE возникает, если кто-то параллельно (например, человек из
 * MetaMask) отправил транзакцию с того же адреса. Кидаем
 * {@see NonceAllocationFailedException::collision}, ретрай делает слой выше.
 *
 * В SQLite (тесты) advisory-lock — no-op; сериализация обеспечена глобальной
 * write-lock SQLite.
 *
 * @see \GUIDE.md  Урок 3 (#урок-3--транзакция-utxo-против-аккаунта)
 * @see \GUIDE.md  Урок 10 (#урок-10--вывод-средств-withdrawal)
 */
final readonly class EloquentNonceAllocator implements NonceAllocator
{
    public function __construct(
        private DatabaseManager $db,
        private NonceProbeRegistry $probes,
    ) {}

    public function allocate(Chain $chain, HotAddress $hot): NonceValue
    {
        $connection = $this->db->connection();

        return $connection->transaction(function () use ($connection, $chain, $hot): NonceValue {
            // Шаг 2 (GUIDE §3): advisory-lock на пару (chain, hot).
            // crc32 → uint32 → строка для биндинга; pg_advisory_xact_lock
            // освободится автоматически при COMMIT/ROLLBACK.
            if ($connection->getDriverName() === 'pgsql') {
                $lockKey = (string) sprintf('%u', crc32($chain->id->value.':'.$hot->value));
                $connection->statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
            }

            // Шаг 3 (GUIDE §3): кандидат «MAX(nonce) + 1» из наших же выданных номеров.
            $maxRaw = NonceAssignmentModel::query()
                ->where('chain_id', $chain->id->value)
                ->where('hot_address', $hot->value)
                ->max('nonce');

            // Шаг 4 (GUIDE §3): если ни одного nonce не выдавали — спрашиваем сеть.
            // Сеть знает истину: сколько транзакций уже подтверждено от адреса.
            if ($maxRaw === null) {
                $probe = $this->probes->for($chain->family);
                $probed = $probe?->probe($chain, $hot);
                $next = $probed === null ? 0 : $probed->value;
            } else {
                $next = ((int) $maxRaw) + 1;
            }

            try {
                NonceAssignmentModel::query()->insert([
                    'chain_id' => $chain->id->value,
                    'hot_address' => $hot->value,
                    'nonce' => $next,
                    'withdrawal_id' => null,
                    'allocated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:sP'),
                    'used_at' => null,
                ]);
            } catch (QueryException $e) {
                // 23505 = PG unique_violation; SQLite даёт сообщение "UNIQUE constraint failed".
                if ($this->isUniqueViolation($e)) {
                    throw NonceAllocationFailedException::collision($chain->id, $hot, $next);
                }
                throw NonceAllocationFailedException::rpc($chain->id, $hot, $e->getMessage(), $e);
            } catch (Throwable $e) {
                throw NonceAllocationFailedException::rpc($chain->id, $hot, $e->getMessage(), $e);
            }

            return new NonceValue($next);
        });
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        if ($sqlState === '23505') {
            return true;
        }
        $msg = $e->getMessage();
        return str_contains($msg, 'UNIQUE constraint failed')
            || str_contains($msg, 'Duplicate entry')
            || str_contains($msg, 'duplicate key');
    }
}
