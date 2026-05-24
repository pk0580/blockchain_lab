<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Repository;

use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\ChainId;

interface LedgerEntryRepository
{
    public function save(LedgerEntry $entry): void;

    public function existsForOperation(OperationType $type, OperationRef $ref): bool;

    /**
     * Confirmed-credit-записи, чьи блоки попали в reorg-диапазон (>= fromHeight),
     * пока ещё не помечены Reversed. Используется reversal-action'ом для
     * генерации встречных проводок.
     *
     * @return list<LedgerEntry>
     */
    public function findCreditsAffectedByReorg(ChainId $chainId, int $fromHeight): array;

    /**
     * @return list<LedgerEntry>
     */
    public function listByWallet(WalletId $walletId): array;
}
