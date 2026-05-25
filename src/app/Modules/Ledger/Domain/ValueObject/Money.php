<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Сумма в minor units сети + валюта.
 *
 * Единицы (GUIDE.md, Урок 3, врезка «Единицы измерения»):
 *   1 BTC  = 10^8  сатоши (sat)
 *   1 ETH  = 10^18 wei
 *   1 TRX  = 10^6  sun
 *
 * ⚠️ Хранится СТРОКОЙ, потому что значения в wei могут быть до 40 десятичных
 * цифр — int64 (макс. ~19 цифр) не хватит. БД: `NUMERIC(40,0)`. См. GUIDE.md,
 * Урок 8 «Сущность LedgerEntry», конец раздела.
 *
 * Арифметики над Money здесь нет — Ledger не считает балансы; этим занимается
 * Wallet read-model. Это намеренно: Money — value object, а не number.
 *
 * @see \GUIDE.md  Урок 3 (#урок-3--транзакция-utxo-против-аккаунта)
 * @see \GUIDE.md  Урок 8 (#урок-8--двойная-бухгалтерия-ledger)
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
