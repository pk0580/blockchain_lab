<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Сумма в minor units сети (wei/satoshi/sun) + валюта. Хранится строкой,
 * потому что значения могут не помещаться в int64 (NUMERIC(40,0) в БД).
 * Никаких арифметических операций над Money здесь нет — Phase 5 ledger
 * не нуждается в сложении балансов; этим займётся Wallet read-model.
 */
final readonly class Money
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
        if (! preg_match('/^[1-9][0-9]{0,39}$|^0$/', $amount)) {
            throw new InvalidArgumentException(
                "Money amount must be a non-negative integer string <=40 digits, got '{$amount}'."
            );
        }
        if (! preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $currency)) {
            throw new InvalidArgumentException(
                "Money currency must be 2-10 uppercase chars, got '{$currency}'."
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
