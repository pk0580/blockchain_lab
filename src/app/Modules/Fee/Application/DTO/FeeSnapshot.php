<?php

declare(strict_types=1);

namespace App\Modules\Fee\Application\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Примитивный снимок {@see \App\Modules\Fee\Domain\ValueObject\FeeQuote},
 * предназначенный для пересечения границ модулей. Withdrawal::Application
 * импортирует ЭТУ DTO, а не Fee::Domain — `ModuleBoundariesTest` запрещает
 * `Module::Application -> Other::Domain` импорты.
 */
final readonly class FeeSnapshot
{
    /**
     * @param array<string, scalar> $breakdown
     */
    public function __construct(
        public string $chainId,
        public string $priority,
        public array $breakdown,
        public DateTimeImmutable $estimatedAt,
    ) {
        if (! isset($breakdown['family']) || ! is_string($breakdown['family']) || $breakdown['family'] === '') {
            throw new InvalidArgumentException('FeeSnapshot breakdown must carry a non-empty string "family" key.');
        }
    }
}
