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
 * Сериализует выдачу nonce для каждой пары (chain, hot) через рекомендательную блокировку
 * PG advisory_xact_lock, затем выполняет `INSERT INTO nonce_assignments` с ограничением
 * уникальности по композитному первичному ключу. В SQLite (тесты) advisory-lock
 * является пустой операцией; сериализация обеспечивается глобальной транзакционной
 * блокировкой SQLite.
 *
 * Алгоритм:
 *   1. BEGIN
 *   2. SELECT MAX(nonce) WHERE chain_id=? AND hot_address=?
 *   3. Если MAX == NULL → зондирование (EVM RPC eth_getTransactionCount, fallback 0)
 *   4. next = MAX + 1 ИЛИ зондированное значение
 *   5. INSERT row(chain_id, hot_address, next, allocated_at=now)
 *   6. COMMIT (рекомендательная блокировка освобождается)
 *
 * При нарушении уникальности (теоретическая гонка между нашим INSERT и внешней
 * системой, отправляющей транзакцию с таким же nonce — например, ручной перевод через
 * Metamask с того же горячего адреса) — выбрасываем NonceAllocationFailedException::collision,
 * вышестоящий механизм повторных попыток обработает это.
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
            if ($connection->getDriverName() === 'pgsql') {
                $lockKey = (string) sprintf('%u', crc32($chain->id->value.':'.$hot->value));
                $connection->statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
            }

            $maxRaw = NonceAssignmentModel::query()
                ->where('chain_id', $chain->id->value)
                ->where('hot_address', $hot->value)
                ->max('nonce');

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
