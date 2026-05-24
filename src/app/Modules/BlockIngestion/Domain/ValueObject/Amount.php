<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Неотрицательная целочисленная сумма в минимальных единицах сети (сатоши для BTC,
 * wei для EVM, sun для Tron). Хранится в виде десятичной строки, чтобы мы никогда не теряли
 * точность на значениях, превышающих PHP_INT_MAX (суммы в wei часто превышают это значение).
 */
final readonly class Amount
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = ltrim($value, '+');
        if ($normalized === '' || ! preg_match('/^\d+$/', $normalized)) {
            throw new InvalidArgumentException("Amount must be a non-negative integer string: '{$value}'.");
        }
        // удаляем ведущие нули, но сохраняем "0"
        $normalized = ltrim($normalized, '0');
        $this->value = $normalized === '' ? '0' : $normalized;
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public function isZero(): bool
    {
        return $this->value === '0';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
