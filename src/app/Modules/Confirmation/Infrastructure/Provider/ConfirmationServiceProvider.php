<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Infrastructure\Provider;

use App\Modules\Confirmation\Domain\Contract\ChainScannerHead;
use App\Modules\Confirmation\Domain\Repository\PendingTransactionRepository;
use App\Modules\Confirmation\Infrastructure\Adapter\EloquentChainScannerHead;
use App\Modules\Confirmation\Infrastructure\Persistence\Eloquent\Repositories\EloquentPendingTransactionRepository;
use Illuminate\Support\ServiceProvider;

final class ConfirmationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PendingTransactionRepository::class,
            EloquentPendingTransactionRepository::class,
        );
        $this->app->singleton(ChainScannerHead::class, EloquentChainScannerHead::class);
    }

    public function boot(): void
    {
    }
}
