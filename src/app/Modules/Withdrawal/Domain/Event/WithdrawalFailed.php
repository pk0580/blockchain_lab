<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

final readonly class WithdrawalFailed
{
    public function __construct(
        public WithdrawalId $id,
        public string $reason,
        public DateTimeImmutable $occurredAt,
    ) {}
}
