<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\ValueObject;

use InvalidArgumentException;

final readonly class HttpMethod
{
    public function __construct(public string $value)
    {
        $upper = strtoupper(trim($value));
        if (preg_match('/^[A-Z]{1,10}$/', $upper) !== 1) {
            throw new InvalidArgumentException(
                "HttpMethod must be 1..10 upper letters, got '{$value}'."
            );
        }
        if ($upper !== $value) {
            throw new InvalidArgumentException(
                "HttpMethod must be already upper-cased, got '{$value}'."
            );
        }
    }
}
