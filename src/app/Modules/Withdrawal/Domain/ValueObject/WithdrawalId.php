<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Идентификатор withdrawal — UUID v4 строкой. Контрольная регулярка ловит
 * опечатки в фикстурах без необходимости подтягивать UUID-библиотеку в Domain.
 */
final readonly class WithdrawalId
{
    public function __construct(public string $value)
    {
        $normalized = strtolower($value);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $normalized) !== 1) {
            throw new InvalidArgumentException("WithdrawalId must be a UUID: '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
