<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Read-side порт «является ли этот адрес нашим?». Должен отвечать в микросекундах:
 * сканер делает десятки тысяч таких проверок на каждый блок (см. GUIDE.md, Урок 5
 * — раздел «Почему Redis, а не PostgreSQL»).
 *
 * Источник правды для адресов — таблица `addresses` (Address::Infrastructure).
 * Этот порт — денормализованная проекция под одну операцию SISMEMBER.
 *
 * Реализации:
 *  - {@see \App\Modules\Address\Infrastructure\AntiCorruption\RedisAddressDirectory} — prod.
 *  - {@see \App\Modules\Address\Infrastructure\AntiCorruption\InMemoryAddressDirectory} — тесты.
 *
 * BlockIngestion никогда не импортирует модуль Address напрямую — только этот порт.
 *
 * @see \GUIDE.md  Урок 5 (#урок-5--сканирование-цепи-и-обнаружение-поступлений)
 */
interface AddressDirectory
{
    public function isWatched(ChainFamily $family, string $address): bool;

    public function register(ChainFamily $family, string $address): void;

    /**
     * Только для тестов / администрирования: очистить кэш для семейства. Полезно при переиндексации.
     */
    public function flush(ChainFamily $family): void;
}
