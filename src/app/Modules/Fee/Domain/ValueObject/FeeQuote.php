<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

/**
 * Котировка комиссии в конкретный момент времени для конкретной цепочки.
 * Хранит снимок (breakdown) и отметку времени — у комиссии есть TTL, потому
 * что условия mempool/baseFee меняются. Phase 6.2 при необходимости введёт
 * re-quote перед broadcast, если quote старше N секунд.
 */
final readonly class FeeQuote
{
    public function __construct(
        public ChainId $chainId,
        public FeePriority $priority,
        public FeeBreakdown $breakdown,
        public DateTimeImmutable $estimatedAt,
    ) {}
}
