<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

/**
 * Поднимается на оригинальном withdrawal, когда RBF/resend создал replacement.
 * `$replacementId` — это новая запись со status=Broadcasted; оригинал переходит
 * в `Replaced`. Downstream observer'ы могут чейнить отображение "1 → 2 → 3"
 * для аналитики стуков.
 */
final readonly class WithdrawalReplaced
{
    public function __construct(
        public WithdrawalId $id,
        public WithdrawalId $replacementId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
