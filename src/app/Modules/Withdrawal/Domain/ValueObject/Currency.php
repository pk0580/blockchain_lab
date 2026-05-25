<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Тикер валюты на withdrawal. Для нативной транзакции (BTC, ETH) совпадает с
 * native currency сети; для ERC-20-токенов (USDC и т.п.) — символ токена.
 */
final readonly class Currency
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $value)) {
            throw new InvalidArgumentException(
                "Currency must be 2-10 uppercase alnum, got '{$value}'."
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
