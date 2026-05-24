<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

/**
 * Поднимается, когда polling job видит ≥1 подтверждение, но финальный
 * порог chain.required_confirmations ещё не достигнут. Помогает observability —
 * downstream listeners могут пушить уведомления "ваш withdrawal в mempool блока N".
 */
final readonly class WithdrawalConfirming
{
    public function __construct(
        public WithdrawalId $id,
        public int $confirmations,
        public DateTimeImmutable $occurredAt,
    ) {}
}
