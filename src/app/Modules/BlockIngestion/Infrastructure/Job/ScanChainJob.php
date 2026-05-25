<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Job;

use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockAction;
use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Обработка сканирования для конкретной сети в очереди `scan.{chain}`. Мы удерживаем кратковременную
 * блокировку кэша, чтобы два воркера не конкурировали за курсор; если блокировка занята,
 * мы молча выходим — следующий тик догонит.
 */
final class ScanChainJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly string $chainId,
        public readonly int $maxBlocksPerTick = 25,
    ) {
        $this->onQueue("scan.{$chainId}");
    }

    public function handle(ScanNextBlockAction $action, CacheRepository $cache): void
    {
        $lockKey = "block-ingestion:scan-lock:{$this->chainId}";
        $store = $cache->getStore();
        if (! method_exists($store, 'lock')) {
            // Запасной вариант: распределенная блокировка недоступна (например, драйвер array
            // во время тестов) — продолжаем без координации.
            $action->handle(new ScanNextBlockData($this->chainId, $this->maxBlocksPerTick));
            return;
        }

        /** @var \Illuminate\Contracts\Cache\Lock $lock */
        $lock = $store->lock($lockKey, 55);
        if (! $lock->get()) {
            return;
        }
        try {
            $action->handle(new ScanNextBlockData($this->chainId, $this->maxBlocksPerTick));
        } finally {
            $lock->release();
        }
    }
}
