<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\AntiCorruption;

use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * Прод-реализация {@see AddressDirectory} через Redis SET для каждого семейства.
 *
 * Почему именно Redis SET (GUIDE.md, Урок 5 — раздел «Почему Redis, а не PostgreSQL»):
 *  - SISMEMBER = O(1) hash-lookup in-memory, десятки микросекунд по сети.
 *  - PostgreSQL дал бы O(log n) + накладные расходы парсинга/MVCC/fsync —
 *    на «горячем пути» сканера это уже боттлнек.
 *  - Шардирование по семейству: `bl:addr:bitcoin`, `bl:addr:evm`, `bl:addr:tron`
 *    — BTC-сканер никогда не «трогает» EVM-набор.
 *
 * ⚠️ Это денормализованная проекция, НЕ источник истины. Источник — таблица
 * `addresses` в PostgreSQL. Если Redis потеряет данные — набор перестраивается
 * (rebuild-команда в roadmap), durability тут не нужна.
 *
 * Поддерживается актуальным через слушатель события AddressGenerated
 * ({@see \App\Modules\Address\Infrastructure\Listener\RegisterAddressInDirectory}).
 *
 * @see \GUIDE.md  Урок 5 (#урок-5--сканирование-цепи-и-обнаружение-поступлений)
 */
final readonly class RedisAddressDirectory implements AddressDirectory
{
    public function __construct(
        private RedisFactory $redis,
        private string $connection,
        private string $keyPrefix,
    ) {}

    public function isWatched(ChainFamily $family, string $address): bool
    {
        if ($address === '') {
            return false;
        }
        $result = $this->redis->connection($this->connection)
            ->command('SISMEMBER', [$this->keyFor($family), $address]);
        return (int) $result === 1;
    }

    public function register(ChainFamily $family, string $address): void
    {
        if ($address === '') {
            return;
        }
        $this->redis->connection($this->connection)
            ->command('SADD', [$this->keyFor($family), $address]);
    }

    public function flush(ChainFamily $family): void
    {
        $this->redis->connection($this->connection)
            ->command('DEL', [$this->keyFor($family)]);
    }

    private function keyFor(ChainFamily $family): string
    {
        return "{$this->keyPrefix}:{$family->value}";
    }
}
