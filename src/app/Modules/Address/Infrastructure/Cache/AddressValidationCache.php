<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Cache;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Тонкая обертка над кэшем Laravel, чтобы освободить экшен валидации от
 * вызовов фасадов и централизовать формат ключей.
 */
final readonly class AddressValidationCache
{
    private const int TTL_SECONDS = 86_400;            // 24 часа

    public function __construct(private CacheRepository $cache) {}

    public function get(ChainFamily $family, string $address): ?bool
    {
        /** @var mixed $hit */
        $hit = $this->cache->get($this->key($family, $address));
        return is_bool($hit) ? $hit : null;
    }

    public function put(ChainFamily $family, string $address, bool $valid): void
    {
        $this->cache->put($this->key($family, $address), $valid, self::TTL_SECONDS);
    }

    private function key(ChainFamily $family, string $address): string
    {
        return 'address:valid:'.$family->value.':'.hash('xxh128', $address);
    }
}
