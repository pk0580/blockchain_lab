<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Содержит строку адреса в "сыром" виде. Синтаксическая валидация для конкретного семейства —
 * задача реализации AddressValidator для соответствующей сети (слой Infrastructure).
 * Здесь мы проверяем только то, что строка не пуста, содержит печатные символы и имеет разумную длину.
 */
final readonly class Address
{
    public function __construct(public string $value)
    {
        $len = strlen($value);
        if ($len < 10 || $len > 128 || ! ctype_print($value)) {
            throw new InvalidArgumentException("Адрес должен содержать от 10 до 128 печатных символов: '{$value}'.");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
