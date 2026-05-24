<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

final readonly class OutboxMessageId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value)) {
            throw new InvalidArgumentException("OutboxMessageId must be a UUID, got '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
