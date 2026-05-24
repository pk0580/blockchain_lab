<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Положительный целочисленный остаток в минимальных единицах сети (sat/wei/sun).
 * Храним строкой — wei может выходить за PHP_INT_MAX, для согласия сравниваем
 * через bccomp.
 */
final readonly class WithdrawalAmount
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[1-9][0-9]{0,39}$/', $value)) {
            throw new InvalidArgumentException(
                "WithdrawalAmount must be a positive integer string up to 40 digits, got '{$value}'."
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
