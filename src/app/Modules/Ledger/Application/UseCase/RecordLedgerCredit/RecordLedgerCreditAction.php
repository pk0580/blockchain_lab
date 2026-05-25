<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\UseCase\RecordLedgerCredit;

use App\Modules\Ledger\Domain\Contract\ConfirmedTransactionView;
use App\Modules\Ledger\Domain\Contract\WalletOwnership;
use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\Exception\WalletOwnershipMissingException;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Запись credit-проводки при подтверждении входящей tx (зачисление на баланс).
 *
 * Алгоритм (GUIDE.md, Урок 8 «Зачисление при подтверждении»):
 *
 *   1. Идемпотентность: проверить, нет ли уже записи
 *      (operationType=Deposit, operationRef=incomingTransactionId).
 *      Если есть — created=false, выходим.
 *   2. Подгрузить данные транзакции через {@see ConfirmedTransactionView}.
 *   3. Через {@see WalletOwnership} найти, какому кошельку принадлежит адрес.
 *      Если нет — {@see WalletOwnershipMissingException}.
 *   4. Создать LedgerEntry::recordDeposit (Direction::Credit, Confirmed).
 *   5. COMMIT → событие LedgerEntryRecorded после COMMIT.
 *
 * Идемпотентность спасает от повторной обработки одного и того же события
 * (at-least-once доставка очередей / повторный диспатч).
 *
 * @see \GUIDE.md  Урок 8 (#урок-8--двойная-бухгалтерия-ledger)
 */
final readonly class RecordLedgerCreditAction
{
    public function __construct(
        private ConfirmedTransactionView $transactions,
        private WalletOwnership $ownership,
        private LedgerEntryRepository $entries,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(RecordLedgerCreditData $data): RecordLedgerCreditResult
    {
        $operationRef = new OperationRef($data->incomingTransactionId);
        if ($this->entries->existsForOperation(OperationType::Deposit, $operationRef)) {
            return new RecordLedgerCreditResult(false, null);
        }

        $tx = $this->transactions->findById($data->incomingTransactionId)
            ?? throw new RuntimeException(
                "Cannot record credit: incoming_transactions.id '{$data->incomingTransactionId}' not found."
            );

        $walletId = $this->ownership->findWalletByAddress($tx->family, $tx->toAddress)
            ?? throw WalletOwnershipMissingException::forAddress($tx->family, $tx->toAddress);

        $entryId = new LedgerEntryId(Str::uuid()->toString());
        $now = new DateTimeImmutable();

        $entry = LedgerEntry::recordDeposit(
            id: $entryId,
            walletId: $walletId,
            chainId: $tx->chainId,
            money: $tx->money,
            operationRef: $operationRef,
            relatedTxHash: $tx->txHash,
            blockHeight: $tx->blockHeight,
            now: $now,
        );

        $this->db->transaction(function () use ($entry): void {
            $this->entries->save($entry);
        });

        $events = $entry->pullPendingEvents();
        $this->db->afterCommit(function () use ($events): void {
            foreach ($events as $event) {
                $this->events->dispatch($event);
            }
        });

        return new RecordLedgerCreditResult(true, $entryId->value);
    }
}
