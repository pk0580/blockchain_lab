<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Непрозрачный идентификатор (opaque identifier), который сервис подписания
 * использует для поиска защищенной мнемоники — для нас это просто строка.
 * Платформа НИКОГДА не видит саму мнемонику.
 *
 * ⚠️ Безопасность: если этот VO попадает в логи или в HTTP-ответ — это НЕ
 * утечка ключей. Без файла `<reference>.sealed` в signing-svc reference не
 * расшифровывает ничего. См. GUIDE.md, Урок 2, раздел «Ссылка на ключ»:
 * «Запись в блокноте бесполезна для вора — без мешка она ничего не открывает».
 *
 * Формат соответствует регулярному выражению referenceRegexp в signing-svc.
 *
 * @see \GUIDE.md  Урок 2 (#урок-2--ключи-адреса-и-hd-кошельки)
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
