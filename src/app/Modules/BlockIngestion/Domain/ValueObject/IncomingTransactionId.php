<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ValueObject;

use InvalidArgumentException;

final readonly class IncomingTransactionId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
            throw new InvalidArgumentException("IncomingTransactionId must be a UUID: '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return strcasecmp($this->value, $other->value) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
