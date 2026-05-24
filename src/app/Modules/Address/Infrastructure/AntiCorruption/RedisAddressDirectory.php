<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\AntiCorruption;

use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * Реализует порт модуля BlockIngestion, используя Redis SET для каждого семейства сетей.
 * Находится в Address::Infrastructure, так как модуль Address владеет источником
 * истины (таблица `addresses`) и является естественным швом между этими двумя модулями.
 *
 * Формат хранения:
 *   key   = "{prefix}:{family}"
 *   value = строки адресов, добавленные через SADD
 *
 * Фаза 4 поддерживает этот список актуальным через слушатель события AddressGenerated.
 * Команда для первоначального заполнения из таблицы addresses появится в Фазе 7.
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
