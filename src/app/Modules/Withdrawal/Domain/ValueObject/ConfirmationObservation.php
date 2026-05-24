<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Срез того, что polling видит у конкретного withdrawal в данный момент.
 * Это не события блокчейна, а наблюдение поверх RPC — отдельный объект потому,
 * что у Bitcoin (`getrawtransaction`) и EVM (`eth_getTransactionByHash` +
 * `eth_blockNumber`) разные источники counter'а, но решающая логика общая:
 * сравниваем `confirmations` с `chain.required_confirmations`.
 *
 * `confirmations = 0` означает «висит в мемпуле» (`pending = true`).
 * `confirmations >= 1` означает «уже в блоке».
 *
 * `dropped = true` — узел не помнит транзакцию (BTC: `verbose 1` без блока + не
 * в мемпуле; EVM: result == null). Это сигнал к Stuck/Failed решению на стороне
 * Application: для свежих withdrawals может означать «ещё не дошло до узла»,
 * для старых — «выкинуто из мемпула». Polling сам решений не принимает.
 */
final readonly class ConfirmationObservation
{
    public function __construct(
        public int $confirmations,
        public bool $dropped,
    ) {
        if ($confirmations < 0) {
            throw new InvalidArgumentException(
                "confirmations must be >= 0, got {$confirmations}."
            );
        }
        if ($dropped && $confirmations > 0) {
            throw new InvalidArgumentException(
                'dropped observation cannot carry confirmations.'
            );
        }
    }

    public static function pending(): self
    {
        return new self(confirmations: 0, dropped: false);
    }

    public static function confirmed(int $confirmations): self
    {
        return new self(confirmations: $confirmations, dropped: false);
    }

    public static function dropped(): self
    {
        return new self(confirmations: 0, dropped: true);
    }

    public function isPending(): bool
    {
        return ! $this->dropped && $this->confirmations === 0;
    }
}
