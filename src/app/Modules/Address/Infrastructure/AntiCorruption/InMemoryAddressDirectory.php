<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\AntiCorruption;

use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Test double для {@see AddressDirectory} — массив в памяти.
 *
 * Тот же интерфейсный контракт, ноль внешних зависимостей — unit/feature тесты
 * могут проверять логику сканера без живого Redis. Это типичная инфраструктурная
 * инверсия: меняется только bind в Service Provider (GUIDE.md, Урок 5 — конец).
 *
 * @see \GUIDE.md  Урок 5 (#урок-5--сканирование-цепи-и-обнаружение-поступлений)
 */
final class InMemoryAddressDirectory implements AddressDirectory
{
    /** @var array<string, array<string, true>> */
    private array $sets = [];

    public function isWatched(ChainFamily $family, string $address): bool
    {
        return isset($this->sets[$family->value][$address]);
    }

    public function register(ChainFamily $family, string $address): void
    {
        if ($address === '') {
            return;
        }
        $this->sets[$family->value][$address] = true;
    }

    public function flush(ChainFamily $family): void
    {
        unset($this->sets[$family->value]);
    }
}
