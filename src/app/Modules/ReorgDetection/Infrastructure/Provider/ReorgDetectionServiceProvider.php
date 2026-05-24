<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Provider;

use App\Modules\BlockIngestion\Domain\Event\BlockIngested;
use App\Modules\ReorgDetection\Domain\Contract\ChainHistory;
use App\Modules\ReorgDetection\Domain\Contract\ReorgWriter;
use App\Modules\ReorgDetection\Infrastructure\Adapter\EloquentChainHistory;
use App\Modules\ReorgDetection\Infrastructure\Adapter\EloquentReorgWriter;
use App\Modules\ReorgDetection\Infrastructure\Listener\EvaluateOnBlockIngested;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

final class ReorgDetectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChainHistory::class, EloquentChainHistory::class);
        $this->app->singleton(ReorgWriter::class, EloquentReorgWriter::class);
    }

    public function boot(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(BlockIngested::class, EvaluateOnBlockIngested::class);
    }
}
