<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use InvalidArgumentException;

final readonly class AddressId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidArgumentException("AddressId must be a UUIDv4: '{$value}'.");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
