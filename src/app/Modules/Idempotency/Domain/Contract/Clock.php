<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\Contract;

use DateTimeImmutable;

/**
 * Источник «сейчас» для модуля. Внутренний — другие модули кладут такие порты
 * в Network/shared kernel или используют `CarbonImmutable::now()` напрямую.
 * Здесь нужен явный порт, чтобы тесты middleware могли фиксировать TTL
 * детерминированно без `Carbon::setTestNow`.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
