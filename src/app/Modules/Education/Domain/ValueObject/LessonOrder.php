<?php

declare(strict_types=1);

namespace App\Modules\Education\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Позиция урока внутри своего модуля (1-based). Берётся из numeric-prefix'а
 * имени файла: `01-what-is-blockchain.md` → 1.
 */
final readonly class LessonOrder
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 99) {
            throw new InvalidArgumentException(
                "LessonOrder must be in 1..99, got {$value}."
            );
        }
    }
}
