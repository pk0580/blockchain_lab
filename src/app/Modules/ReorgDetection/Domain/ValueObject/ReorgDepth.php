<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Глубина зафиксированного reorg-эпизода в блоках. Каждое срабатывание
 * ChainComparator трактуется как «глубина = 1» (один orphan на тик сканера);
 * накапливать общее значение — задача потребителя событий.
 */
final readonly class ReorgDepth
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException("ReorgDepth must be >= 1, got {$value}.");
        }
    }
}
