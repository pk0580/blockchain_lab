<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Infrastructure\Provider;

use App\Modules\Idempotency\Domain\Contract\Clock;
use App\Modules\Idempotency\Domain\Contract\IdempotencyStore;
use App\Modules\Idempotency\Infrastructure\Persistence\Eloquent\EloquentIdempotencyStore;
use App\Modules\Idempotency\Infrastructure\Support\SystemClock;
use Illuminate\Support\ServiceProvider;

final class IdempotencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdempotencyStore::class, EloquentIdempotencyStore::class);
        $this->app->singleton(Clock::class, SystemClock::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');
    }
}
