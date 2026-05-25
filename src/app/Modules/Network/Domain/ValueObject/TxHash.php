<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Хеш транзакции — детерминированный отпечаток сериализованной транзакции.
 * Возвращается сетью при broadcast и используется как единственный «адрес»,
 * по которому можно найти транзакцию: `eth_getTransactionByHash` (EVM),
 * `getrawtransaction` (Bitcoin).
 *
 * В Bitcoin исторический хеш и witness-хеш могут различаться (txid vs wtxid);
 * для пользователей и для broadcast мы используем txid.
 *
 * @see \GUIDE.md  Урок 1 (#урок-1--что-такое-блокчейн)
 * @see \GUIDE.md  Урок 6 (#урок-6--подтверждения-и-финализация) — наблюдение по hash
 */
final readonly class TxHash
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^(0x)?[0-9a-fA-F]{40,128}$/', $value)) {
            throw new InvalidArgumentException("TxHash must be hex (40-128 hex chars): '{$value}'.");
        }
    }

    public function normalized(): string
    {
        return strtolower(str_starts_with($this->value, '0x') ? $this->value : '0x'.$this->value);
    }
}
