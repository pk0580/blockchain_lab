<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

/**
 * Котировка комиссии в конкретный момент времени для конкретной цепочки.
 *
 * `breakdown` — полиморфный объект, конкретный класс зависит от семейства
 * (см. GUIDE.md, Урок 9, итоговая таблица «Bitcoin vs EVM»):
 *   - {@see BitcoinFeeBreakdown} → sat/vbyte
 *   - {@see EvmFeeBreakdown}     → max_fee_per_gas, max_priority_fee_per_gas, gas_limit
 *
 * Это позволяет одному {@see \App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot}
 * лежать в `withdrawal.signing_extras` для любой сети.
 *
 * `estimatedAt` — у котировки есть TTL: условия mempool/base_fee меняются.
 *
 * @see \GUIDE.md  Урок 9 (#урок-9--комиссия-fee)
 */
final readonly class FeeQuote
{
    public function __construct(
        public ChainId $chainId,
        public FeePriority $priority,
        public FeeBreakdown $breakdown,
        public DateTimeImmutable $estimatedAt,
    ) {}
}
