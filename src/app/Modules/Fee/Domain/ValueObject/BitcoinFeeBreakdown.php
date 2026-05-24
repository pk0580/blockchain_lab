<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use InvalidArgumentException;

/**
 * Bitcoin-style комиссия: сатоши за виртуальный байт. Виртуальный байт
 * учитывает Segregated Witness discount (witness data весит 1/4 при подсчёте
 * weight). Конечная комиссия = satPerVbyte * vsize(tx).
 *
 * Минимум 1 sat/vB соответствует bitcoind defaults (`minrelaytxfee` обычно
 * 0.00001 BTC/kB = 1 sat/vB). Меньше — нода не примет в mempool.
 */
final readonly class BitcoinFeeBreakdown implements FeeBreakdown
{
    public function __construct(public int $satPerVbyte)
    {
        if ($satPerVbyte < 1) {
            throw new InvalidArgumentException(
                "satPerVbyte должен быть >= 1 (минимум для релея в mempool), получено {$satPerVbyte}."
            );
        }
    }

    public function family(): ChainFamily
    {
        return ChainFamily::Bitcoin;
    }

    /**
     * @return array{family: string, sat_per_vbyte: int}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family()->value,
            'sat_per_vbyte' => $this->satPerVbyte,
        ];
    }
}
