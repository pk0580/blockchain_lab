<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * EVM-style nonce: монотонно растущее неотрицательное целое.
 * Bitcoin не использует nonce (UTXO модель) — там NonceAllocator не вызывается.
 *
 * Храним int: даже 1 transaction в секунду в течение 100 лет = 3.15e9,
 * что укладывается в int32. PG колонка BIGINT даёт запас на ошибки.
 */
final readonly class NonceValue
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "NonceValue must be >= 0, got {$value}."
            );
        }
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
