<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

use InvalidArgumentException;

final readonly class LedgerEntryId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            throw new InvalidArgumentException("LedgerEntryId must be a UUID, got '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return strcasecmp($this->value, $other->value) === 0;
    }
}
