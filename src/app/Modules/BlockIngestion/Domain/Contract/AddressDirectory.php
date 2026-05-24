<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Порт стороны чтения (Read-side): "является ли этот адрес нашим?". Дает быстрый ответ
 * для каждого потенциального выхода в просканированном блоке. В Фазе 4 это реализуется
 * через Redis SET для каждого семейства (SADD / SISMEMBER), который наполняется модулем
 * Address через слушателя события `AddressGenerated`. BlockIngestion никогда не импортирует
 * модуль Address — он взаимодействует только с этим контрактом.
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
