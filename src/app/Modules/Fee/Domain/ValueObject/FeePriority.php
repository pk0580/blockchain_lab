<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Уровень приоритета комиссии. Маппинг в конкретные параметры сети живёт в
 * Infrastructure (sat/vbyte цели для BTC, percentiles eth_feeHistory для EVM).
 *
 * Сознательно три ступени: студенту легко связать с UX в кошельках типа
 * Electrum или MetaMask — там тоже Low / Standard / High.
 */
enum FeePriority: string
{
    case Low = 'low';
    case Standard = 'standard';
    case High = 'high';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new InvalidArgumentException("Unknown fee priority: '{$value}'.");
    }
}
