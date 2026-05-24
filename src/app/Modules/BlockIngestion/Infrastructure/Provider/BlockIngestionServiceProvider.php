<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Provider;

use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\BlockIngestion\Domain\Repository\BlockRepository;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Domain\Repository\ScanCursorRepository;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinCoreBlockSourceFactory;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Repositories\EloquentBlockRepository;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Repositories\EloquentIncomingTransactionRepository;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Repositories\EloquentScanCursorRepository;
use App\Modules\BlockIngestion\UI\Console\InitCursorCommand;
use App\Modules\BlockIngestion\UI\Console\ScanCommand;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class BlockIngestionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRepository::class, EloquentBlockRepository::class);
        $this->app->singleton(ScanCursorRepository::class, EloquentScanCursorRepository::class);
        $this->app->singleton(IncomingTransactionRepository::class, EloquentIncomingTransactionRepository::class);

        $this->app->singleton(BlockSourceFactory::class, function ($app): BlockSourceFactory {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');

            return new BitcoinCoreBlockSourceFactory(
                http: $app->make(HttpFactory::class),
                defaultUser: (string) $config->get('block_ingestion.bitcoin.rpc_user', ''),
                defaultPassword: (string) $config->get('block_ingestion.bitcoin.rpc_password', ''),
                timeoutSeconds: (int) $config->get('block_ingestion.bitcoin.timeout_seconds', 5),
                connectTimeoutSeconds: (int) $config->get('block_ingestion.bitcoin.connect_timeout_seconds', 2),
                retries: (int) $config->get('block_ingestion.bitcoin.retries', 1),
                retryBackoffMs: (int) $config->get('block_ingestion.bitcoin.retry_backoff_ms', 150),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InitCursorCommand::class,
                ScanCommand::class,
            ]);
        }
    }
}
