<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Клиентский ключ идемпотентности (HTTP-header `Idempotency-Key`).
 * Локальный для модуля Idempotency — Withdrawal::Domain имеет собственный VO с
 * теми же правилами, но это сознательное дублирование: модули не делят Domain.
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
        if (preg_match('/^[A-Za-z0-9_\-:.]+$/', $value) !== 1) {
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
