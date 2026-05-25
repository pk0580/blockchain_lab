<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Entity;

use App\Modules\Ledger\Domain\Event\LedgerEntryRecorded;
use App\Modules\Ledger\Domain\Event\LedgerEntryReversed;
use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\EntryStatus;
use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;
use DomainException;

/**
 * Иммутабельная запись двойной бухгалтерии — единица учёта в Ledger.
 *
 * Поля (GUIDE.md, Урок 8 «Сущность LedgerEntry»):
 *   - direction      — Credit (приход) / Debit (расход)
 *   - money          — {@see Money} (сумма строкой + валюта)
 *   - operationType  — Deposit / ReorgReversal
 *   - operationRef   — уникальная ссылка на источник:
 *                       Deposit       → incomingTransactionId
 *                       ReorgReversal → "reorg:{originalEntryId}"
 *   - status         — Confirmed / Pending / Reversed
 *   - reversesEntryId — ссылка на оригинал, если это компенсация.
 *
 * ⚠️ Главный принцип (GUIDE §8): ничего никогда не удаляем. Reorg НЕ удаляет
 * confirmed-запись — создаётся встречная reversal, оригинал помечается
 * Reversed. Аудит «3 мая зачислили, 4 мая сеть отменила» сохраняется навсегда.
 *
 * Баланс кошелька = Σ(Credit.Confirmed) − Σ(Debit.Confirmed) по всем записям
 * с этим walletId. Это read-model.
 *
 * @see \GUIDE.md  Урок 8 (#урок-8--двойная-бухгалтерия-ledger)
 */
final class LedgerEntry
{
    /** @var list<object> */
    private array $pendingEvents = [];

    private function __construct(
        public readonly LedgerEntryId $id,
        public readonly WalletId $walletId,
        public readonly ChainId $chainId,
        public readonly Direction $direction,
        public readonly Money $money,
        public readonly OperationType $operationType,
        public readonly OperationRef $operationRef,
        public readonly ?TxHash $relatedTxHash,
        public readonly ?int $blockHeight,
        public readonly ?LedgerEntryId $reversesEntryId,
        private EntryStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function recordDeposit(
        LedgerEntryId $id,
        WalletId $walletId,
        ChainId $chainId,
        Money $money,
        OperationRef $operationRef,
        TxHash $relatedTxHash,
        int $blockHeight,
        DateTimeImmutable $now,
    ): self {
        $entry = new self(
            id: $id,
            walletId: $walletId,
            chainId: $chainId,
            direction: Direction::Credit,
            money: $money,
            operationType: OperationType::Deposit,
            operationRef: $operationRef,
            relatedTxHash: $relatedTxHash,
            blockHeight: $blockHeight,
            reversesEntryId: null,
            status: EntryStatus::Confirmed,
            createdAt: $now,
        );

        $entry->pendingEvents[] = new LedgerEntryRecorded(
            entryId: $id,
            walletId: $walletId,
            chainId: $chainId,
            direction: Direction::Credit,
            money: $money,
            operationType: OperationType::Deposit,
            occurredAt: $now,
        );

        return $entry;
    }

    public static function reverse(
        LedgerEntryId $id,
        LedgerEntry $original,
        OperationRef $operationRef,
        DateTimeImmutable $now,
    ): self {
        if ($original->status === EntryStatus::Reversed) {
            throw new DomainException(
                "Cannot reverse an already reversed ledger entry {$original->id->value}."
            );
        }

        $entry = new self(
            id: $id,
            walletId: $original->walletId,
            chainId: $original->chainId,
            direction: $original->direction->opposite(),
            money: $original->money,
            operationType: OperationType::ReorgReversal,
            operationRef: $operationRef,
            relatedTxHash: $original->relatedTxHash,
            blockHeight: $original->blockHeight,
            reversesEntryId: $original->id,
            status: EntryStatus::Confirmed,
            createdAt: $now,
        );

        $original->markReversed();

        $entry->pendingEvents[] = new LedgerEntryReversed(
            entryId: $id,
            originalEntryId: $original->id,
            walletId: $original->walletId,
            chainId: $original->chainId,
            money: $original->money,
            occurredAt: $now,
        );

        return $entry;
    }

    public static function reconstitute(
        LedgerEntryId $id,
        WalletId $walletId,
        ChainId $chainId,
        Direction $direction,
        Money $money,
        OperationType $operationType,
        OperationRef $operationRef,
        ?TxHash $relatedTxHash,
        ?int $blockHeight,
        ?LedgerEntryId $reversesEntryId,
        EntryStatus $status,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $walletId,
            $chainId,
            $direction,
            $money,
            $operationType,
            $operationRef,
            $relatedTxHash,
            $blockHeight,
            $reversesEntryId,
            $status,
            $createdAt,
        );
    }

    public function status(): EntryStatus
    {
        return $this->status;
    }

    public function markReversed(): void
    {
        if ($this->status === EntryStatus::Reversed) {
            return;
        }
        if ($this->status === EntryStatus::Pending) {
            throw new DomainException(
                "Cannot reverse a pending ledger entry {$this->id->value}."
            );
        }
        $this->status = EntryStatus::Reversed;
    }

    /**
     * @return list<object>
     */
    public function pullPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }
}
