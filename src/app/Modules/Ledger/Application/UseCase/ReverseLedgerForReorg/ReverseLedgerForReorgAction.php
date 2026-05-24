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
 * Создаёт компенсирующие debit-проводки для каждой confirmed credit-записи,
 * чей блок попал в reorg-диапазон. Оригинальная запись помечается Reversed —
 * НЕ удаляется (audit trail сохраняется навечно).
 *
 * Идемпотентна по выбору источника: репозиторий возвращает только записи в
 * статусе Confirmed; уже Reversed повторно не обрабатываются.
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
