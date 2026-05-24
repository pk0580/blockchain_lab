<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\ValueObject;

use InvalidArgumentException;

/**
 * HTTP path запроса. Хранится без query string (он не должен влиять на
 * идемпотентность — она определяется ключом + body).
 */
final readonly class HttpPath
{
    public const int MAX_LEN = 500;

    public function __construct(public string $value)
    {
        $len = strlen($value);
        if ($len === 0 || $len > self::MAX_LEN) {
            throw new InvalidArgumentException(
                "HttpPath length must be 1..".self::MAX_LEN." chars, got {$len}."
            );
        }
        if ($value[0] !== '/') {
            throw new InvalidArgumentException(
                "HttpPath must start with '/', got '{$value}'."
            );
        }
    }
}
