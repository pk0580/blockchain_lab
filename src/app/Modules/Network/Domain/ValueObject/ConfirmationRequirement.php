<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ConfirmationRequirement
{
    public function __construct(
        public int $requiredConfirmations,
        public int $maxReorgDepth,
    ) {
        if ($requiredConfirmations < 1 || $requiredConfirmations > 1000) {
            throw new InvalidArgumentException("requiredConfirmations out of range: {$requiredConfirmations}.");
        }
        if ($maxReorgDepth < 1 || $maxReorgDepth > 10_000) {
            throw new InvalidArgumentException("maxReorgDepth out of range: {$maxReorgDepth}.");
        }
        if ($maxReorgDepth < $requiredConfirmations) {
            throw new InvalidArgumentException(
                'maxReorgDepth must be >= requiredConfirmations '
                ."(got {$maxReorgDepth} < {$requiredConfirmations})."
            );
        }
    }

    public function isFinal(int $observedConfirmations): bool
    {
        return $observedConfirmations >= $this->requiredConfirmations;
    }
}
