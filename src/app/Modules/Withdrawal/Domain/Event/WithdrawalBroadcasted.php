<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

final readonly class WithdrawalBroadcasted
{
    public function __construct(
        public WithdrawalId $id,
        public TxHash $txHash,
        public DateTimeImmutable $occurredAt,
    ) {}
}
