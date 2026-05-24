<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Event;

use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final readonly class LedgerEntryReversed
{
    public function __construct(
        public LedgerEntryId $entryId,
        public LedgerEntryId $originalEntryId,
        public WalletId $walletId,
        public ChainId $chainId,
        public Money $money,
        public DateTimeImmutable $occurredAt,
    ) {}
}
