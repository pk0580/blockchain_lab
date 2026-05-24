<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Pause;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Реализация поверх Laravel Cache (Redis в проде, array-store в тестах).
 * Pause-state — не транзакционная и не критичная для целостности: дубликат
 * SET-команды нормален, TTL продлевается.
 */
final readonly class CacheChainPauseRegistry implements ChainPauseRegistry
{
    public function __construct(private CacheRepository $cache) {}

    public function isPaused(ChainId $chainId): bool
    {
        return $this->cache->has($this->key($chainId));
    }

    public function pause(ChainId $chainId, int $ttlSeconds): void
    {
        $this->cache->put($this->key($chainId), '1', $ttlSeconds);
    }

    public function clear(ChainId $chainId): void
    {
        $this->cache->forget($this->key($chainId));
    }

    private function key(ChainId $chainId): string
    {
        return "withdrawal:chain_paused:{$chainId->value}";
    }
}
