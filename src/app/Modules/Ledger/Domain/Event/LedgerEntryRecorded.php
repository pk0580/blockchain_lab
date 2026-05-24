<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Event;

use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final readonly class LedgerEntryRecorded
{
    public function __construct(
        public LedgerEntryId $entryId,
        public WalletId $walletId,
        public ChainId $chainId,
        public Direction $direction,
        public Money $money,
        public OperationType $operationType,
        public DateTimeImmutable $occurredAt,
    ) {}
}
