<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Высота блока — порядковый номер блока в цепочке, начиная с 0 (генезис).
 *
 * Используется как координата при сканировании (ScanCursor движется
 * по одной высоте за тик — см. GUIDE.md, Урок 5) и при подсчёте подтверждений
 * (`confirmations = lastScannedHeight - txBlockHeight + 1` — GUIDE.md, Урок 6).
 *
 * @see \GUIDE.md  Урок 1 (#урок-1--что-такое-блокчейн)
 */
final readonly class BlockHeight
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("BlockHeight cannot be negative: {$value}.");
        }
    }

    /** Следующая высота (на единицу выше). Курсор сканера продвигается строго по +1. */
    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Расстояние от текущей высоты до вершины цепи.
     * Используется в формуле подтверждений: `confirmations = head - txHeight + 1`.
     */
    public function distanceTo(self $head): int
    {
        return max(0, $head->value - $this->value);
    }
}
