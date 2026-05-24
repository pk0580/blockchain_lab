<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

final readonly class WithdrawalRequested
{
    public function __construct(
        public WithdrawalId $id,
        public ChainId $chainId,
        public WalletId $walletId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
