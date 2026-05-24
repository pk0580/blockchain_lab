<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\EntryStatus;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Mappers\LedgerEntryMapper;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\LedgerEntryModel;
use App\Modules\Network\Domain\ValueObject\ChainId;

final readonly class EloquentLedgerEntryRepository implements LedgerEntryRepository
{
    public function __construct(private LedgerEntryMapper $mapper) {}

    public function save(LedgerEntry $entry): void
    {
        $row = $this->mapper->toRow($entry);
        LedgerEntryModel::query()->updateOrInsert(['id' => $row['id']], $row);
    }

    public function existsForOperation(OperationType $type, OperationRef $ref): bool
    {
        return LedgerEntryModel::query()
            ->where('operation_type', $type->value)
            ->where('operation_ref', $ref->value)
            ->exists();
    }

    /**
     * @return list<LedgerEntry>
     */
    public function findCreditsAffectedByReorg(ChainId $chainId, int $fromHeight): array
    {
        return array_values(
            LedgerEntryModel::query()
                ->where('chain_id', $chainId->value)
                ->where('direction', Direction::Credit->value)
                ->where('status', EntryStatus::Confirmed->value)
                ->where('operation_type', OperationType::Deposit->value)
                ->whereNotNull('block_height')
                ->where('block_height', '>=', $fromHeight)
                ->orderBy('created_at')
                ->get()
                ->map($this->mapper->toDomain(...))
                ->all(),
        );
    }

    /**
     * @return list<LedgerEntry>
     */
    public function listByWallet(WalletId $walletId): array
    {
        return array_values(
            LedgerEntryModel::query()
                ->where('wallet_id', $walletId->value)
                ->orderBy('created_at')
                ->get()
                ->map($this->mapper->toDomain(...))
                ->all(),
        );
    }
}
