<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class NativeCurrency
{
    public function __construct(
        public string $symbol,
        public int $decimals,
    ) {
        if (! preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $symbol)) {
            throw new InvalidArgumentException("Currency symbol must be uppercase, 2-10 chars: '{$symbol}'.");
        }
        if ($decimals < 0 || $decimals > 30) {
            throw new InvalidArgumentException("Decimals out of range (0..30): {$decimals}.");
        }
    }
}
