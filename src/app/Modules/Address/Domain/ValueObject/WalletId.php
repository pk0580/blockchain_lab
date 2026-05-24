<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Forward declaration for the Wallet bounded context (Phase 5). Address
 * holds a WalletId reference but does not reach into Wallet — the actual
 * Wallet entity is foreign to this module.
 */
final readonly class WalletId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidArgumentException("WalletId must be a UUIDv4: '{$value}'.");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
