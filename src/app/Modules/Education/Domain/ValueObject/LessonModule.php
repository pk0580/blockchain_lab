<?php

declare(strict_types=1);

namespace App\Modules\Education\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Раздел курса: `01-foundations`, `02-bitcoin`, ... Совпадает с именем
 * директории под `content/lessons/`. Порядок берётся из numeric-prefix'а.
 */
final readonly class LessonModule
{
    public function __construct(public string $value)
    {
        if (preg_match('/^\d{2}-[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "LessonModule must look like 'NN-name', got '{$value}'."
            );
        }
    }

    public function order(): int
    {
        return (int) substr($this->value, 0, 2);
    }

    public function displayName(): string
    {
        $rest = substr($this->value, 3);
        return str_replace('-', ' ', $rest);
    }
}
