<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Непрозрачный идентификатор (opaque identifier), который сервис подписания использует для поиска защищенной
 * мнемоники — для нас это просто строка. Платформа НИКОГДА не видит саму
 * мнемонику. Формат соответствует регулярному выражению referenceRegexp в сервисе подписания.
 */
final readonly class HdSeedReference
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{4,64}$/', $value)) {
            throw new InvalidArgumentException("HdSeedReference должен соответствовать шаблону [a-zA-Z0-9_-]{4,64}: '{$value}'.");
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
