<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\UseCase\ReverseLedgerForReorg;

use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Откат проводок при реорге: создаёт встречные debit-записи для каждой
 * confirmed credit, чей блок попал в orphaned-диапазон.
 *
 * Алгоритм (GUIDE.md, Урок 8 «Откат при реорге»):
 *
 *   1. Найти все LedgerEntry, у которых:
 *      - chainId совпадает,
 *      - blockHeight >= fromHeight (попадают в orphaned диапазон),
 *      - status == Confirmed.
 *   2. Для каждого:
 *      - Создать новую запись LedgerEntry::reverse(original):
 *           direction = original.direction.opposite() (Credit → Debit)
 *           operationType = ReorgReversal
 *           reversesEntryId = original.id
 *      - Оригинал перевести в Reversed.
 *   3. Поднять события LedgerEntryReversed после COMMIT.
 *
 * ⚠️ Оригинал НЕ удаляется (GUIDE §8): можно показать клиенту полную историю
 * «3 мая зачислили 0.5 BTC → 4 мая блок отменён сетью → отменено компенсирующей
 * проводкой».
 *
 * Идемпотентность: репозиторий возвращает только Confirmed; уже Reversed
 * повторно не обрабатываются.
 *
 * @see \GUIDE.md  Урок 8 (#урок-8--двойная-бухгалтерия-ledger)
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
 */
final readonly class ReverseLedgerForReorgAction
{
    public function __construct(
        private LedgerEntryRepository $entries,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(ReverseLedgerForReorgData $data): ReverseLedgerForReorgResult
    {
        $chainId = new ChainId($data->chainId);
        $affected = $this->entries->findCreditsAffectedByReorg($chainId, $data->fromHeight);

        if ($affected === []) {
            return new ReverseLedgerForReorgResult($data->chainId, $data->fromHeight, 0);
        }

        $now = new DateTimeImmutable();
        /** @var list<object> $pendingEvents */
        $pendingEvents = [];

        $this->db->transaction(function () use ($affected, $now, &$pendingEvents): void {
            foreach ($affected as $original) {
                $reversalRef = new OperationRef('reorg:'.$original->id->value);
                $reversal = LedgerEntry::reverse(
                    id: new LedgerEntryId(Str::uuid()->toString()),
                    original: $original,
                    operationRef: $reversalRef,
                    now: $now,
                );

                $this->entries->save($reversal);
                $this->entries->save($original);

                foreach ($reversal->pullPendingEvents() as $event) {
                    $pendingEvents[] = $event;
                }
            }
        });

        $this->db->afterCommit(function () use ($pendingEvents): void {
            foreach ($pendingEvents as $event) {
                $this->events->dispatch($event);
            }
        });

        return new ReverseLedgerForReorgResult(
            chainId: $data->chainId,
            fromHeight: $data->fromHeight,
            reversedEntries: count($affected),
        );
    }
}
