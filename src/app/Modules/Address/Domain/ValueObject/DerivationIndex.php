<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Неотрицательное целое число, используемое в качестве последнего шага (листа) пути вывода BIP-44.
 * Диапазон hardened (>= 2^31) зарезервирован для шагов уровня аккаунта и здесь
 * отклоняется: адреса всегда являются non-hardened листами.
 */
final readonly class DerivationIndex
{
    public const int MAX = 0x7FFFFFFF; // 2^31 - 1

    public function __construct(public int $value)
    {
        if ($value < 0 || $value > self::MAX) {
            throw new InvalidArgumentException("DerivationIndex вне диапазона [0..2^31-1]: {$value}.");
        }
    }

    public function next(): self
    {
        if ($this->value === self::MAX) {
            throw new InvalidArgumentException('Переполнение DerivationIndex: невозможно увеличить значение сверх 2^31-1.');
        }
        return new self($this->value + 1);
    }
}
