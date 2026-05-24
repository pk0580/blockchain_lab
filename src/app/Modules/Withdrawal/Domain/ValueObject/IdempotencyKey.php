<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Клиентский ключ идемпотентности (HTTP-header `Idempotency-Key`).
 * Длина — компромисс между UX (UUID, ULID, KSUID...) и SQL-индексом.
 * Уникальность подкреплена UNIQUE-индексом на `withdrawals.idempotency_key`.
 */
final readonly class IdempotencyKey
{
    public function __construct(public string $value)
    {
        $len = strlen($value);
        if ($len < 8 || $len > 120) {
            throw new InvalidArgumentException(
                "IdempotencyKey length must be 8..120 chars, got {$len}."
            );
        }
        if (! preg_match('/^[A-Za-z0-9_\-:.]+$/', $value)) {
            throw new InvalidArgumentException(
                "IdempotencyKey contains forbidden characters: '{$value}'."
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
