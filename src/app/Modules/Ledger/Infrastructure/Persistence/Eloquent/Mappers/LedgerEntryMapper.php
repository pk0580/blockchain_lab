<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\EntryStatus;
use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\LedgerEntryModel;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;

final class LedgerEntryMapper
{
    public function toDomain(LedgerEntryModel $row): LedgerEntry
    {
        return LedgerEntry::reconstitute(
            id: new LedgerEntryId($row->id),
            walletId: new WalletId($row->wallet_id),
            chainId: new ChainId($row->chain_id),
            direction: Direction::from($row->direction),
            money: new Money($this->amountToString($row->amount), $row->currency),
            operationType: OperationType::from($row->operation_type),
            operationRef: new OperationRef($row->operation_ref),
            relatedTxHash: $row->related_tx_hash !== null ? new TxHash($row->related_tx_hash) : null,
            blockHeight: $row->block_height !== null ? (int) $row->block_height : null,
            reversesEntryId: $row->reverses_entry_id !== null
                ? new LedgerEntryId($row->reverses_entry_id)
                : null,
            status: EntryStatus::from($row->status),
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
        );
    }

    /**
     * @return array{
     *     id: string, wallet_id: string, chain_id: string,
     *     direction: string, amount: string, currency: string,
     *     operation_type: string, operation_ref: string,
     *     related_tx_hash: string|null, block_height: int|null,
     *     reverses_entry_id: string|null, status: string,
     *     created_at: \DateTimeImmutable,
     * }
     */
    public function toRow(LedgerEntry $entry): array
    {
        return [
            'id' => $entry->id->value,
            'wallet_id' => $entry->walletId->value,
            'chain_id' => $entry->chainId->value,
            'direction' => $entry->direction->value,
            'amount' => $entry->money->amount,
            'currency' => $entry->money->currency,
            'operation_type' => $entry->operationType->value,
            'operation_ref' => $entry->operationRef->value,
            'related_tx_hash' => $entry->relatedTxHash?->normalized(),
            'block_height' => $entry->blockHeight,
            'reverses_entry_id' => $entry->reversesEntryId?->value,
            'status' => $entry->status()->value,
            'created_at' => $entry->createdAt,
        ];
    }

    private function amountToString(string|int|float $raw): string
    {
        if (is_int($raw)) {
            return (string) $raw;
        }
        if (is_float($raw)) {
            return number_format($raw, 0, '.', '');
        }
        if (str_contains($raw, '.')) {
            $integer = explode('.', $raw, 2)[0];
            return $integer === '' ? '0' : $integer;
        }
        return $raw;
    }
}
