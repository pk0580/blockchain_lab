<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use InvalidArgumentException;

final readonly class HdSeedId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidArgumentException("HdSeedId must be a UUIDv4: '{$value}'.");
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
