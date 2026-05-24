<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ChainId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,62}[a-z0-9]$/', $value)) {
            throw new InvalidArgumentException(
                "ChainId must be lowercase kebab-case, 3-64 chars: '{$value}'."
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
