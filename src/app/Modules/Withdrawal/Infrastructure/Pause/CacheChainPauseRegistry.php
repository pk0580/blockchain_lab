<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Pause;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Хранилище паузы сети — поверх Laravel Cache (Redis в проде, array в тестах).
 *
 * Используется для «остановить все исходящие на эту сеть» при подозрительных
 * условиях (слишком глубокий reorg, ручной аварийный stop). См. GUIDE.md,
 * Урок 11 «Пауза сети» и Урок 7 «Реакция других модулей».
 *
 * Не транзакционная и не критичная для целостности: дубликат SET-команды
 * нормален, TTL продлевается. Cache::flush() в тестах сбрасывает паузы.
 *
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
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
