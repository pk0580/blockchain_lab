<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Адрес «горячего» кошелька, с которого отправляются исходящие транзакции.
 * Хранится строкой — формат зависит от family (BTC base58/bech32, EVM hex),
 * валидация по семейству — забота SigningClient/AddressValidator.
 *
 * Локальный VO Withdrawal::Domain: не импортируем `Network::Domain::Address`,
 * чтобы Withdrawal оставался автономным относительно кросс-модульной
 * сериализации (Address VO в Network — это shared kernel, но здесь нам не
 * нужен его весь объём — только структурное хранение).
 */
final readonly class HotAddress
{
    public function __construct(public string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 96) {
            throw new InvalidArgumentException(
                "HotAddress must be a non-empty string up to 96 chars, got '{$value}'."
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
