<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Currency
{
    public string $code;

    public function __construct(string $code)
    {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^[A-Z0-9]{2,10}$/', $normalized)) {
            throw new InvalidArgumentException("Currency must be 2-10 alnum chars: '{$code}'.");
        }
        $this->code = $normalized;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
