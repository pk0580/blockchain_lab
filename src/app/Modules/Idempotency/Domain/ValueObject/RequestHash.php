<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\ValueObject;

use InvalidArgumentException;

/**
 * SHA-256 хеш канонизированного запроса (method + path + raw body).
 * Хранится как нижний-регистр hex (64 chars). Сравнение через `hash_equals`
 * чтобы не оставить timing side-channel на cache replay.
 */
final readonly class RequestHash
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "RequestHash must be 64-char lower-hex sha256, got '{$value}'."
            );
        }
    }

    public static function ofRequest(string $method, string $path, string $body): self
    {
        $canonical = strtoupper($method)."\n".$path."\n".$body;
        return new self(hash('sha256', $canonical));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
