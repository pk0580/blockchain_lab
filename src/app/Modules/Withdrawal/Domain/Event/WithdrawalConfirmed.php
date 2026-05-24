<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

/**
 * Поднимается, когда polling job достиг chain.required_confirmations. Терминальное
 * для прямой ветки состояние; downstream listener'ы могут отписывать пользователю
 * или закрывать связанные orchestration-цепочки.
 */
final readonly class WithdrawalConfirmed
{
    public function __construct(
        public WithdrawalId $id,
        public int $confirmations,
        public DateTimeImmutable $occurredAt,
    ) {}
}
