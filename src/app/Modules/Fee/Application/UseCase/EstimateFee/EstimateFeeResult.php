<?php

declare(strict_types=1);

namespace App\Modules\Fee\Application\UseCase\EstimateFee;

use App\Modules\Fee\Application\DTO\FeeSnapshot;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;

final readonly class EstimateFeeResult
{
    public function __construct(public FeeQuote $quote) {}

    /**
     * Cross-module-friendly view of the quote: только примитивы, без импортов
     * Fee::Domain снаружи.
     */
    public function toSnapshot(): FeeSnapshot
    {
        return new FeeSnapshot(
            chainId: $this->quote->chainId->value,
            priority: $this->quote->priority->value,
            breakdown: $this->quote->breakdown->toArray(),
            estimatedAt: $this->quote->estimatedAt,
        );
    }
}
