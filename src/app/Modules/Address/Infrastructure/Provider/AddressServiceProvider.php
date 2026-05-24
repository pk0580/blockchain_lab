<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Provider;

use App\Modules\Address\Domain\Event\AddressGenerated;
use App\Modules\Address\Domain\Repository\AddressRepository;
use App\Modules\Address\Domain\Repository\HdSeedRepository;
use App\Modules\Address\Infrastructure\AntiCorruption\InMemoryAddressDirectory;
use App\Modules\Address\Infrastructure\AntiCorruption\RedisAddressDirectory;
use App\Modules\Address\Infrastructure\Listener\RegisterAddressInDirectory;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Repositories\EloquentAddressRepository;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Repositories\EloquentHdSeedRepository;
use App\Modules\Address\UI\Console\CreateHdSeedCommand;
use App\Modules\Address\UI\Console\GenerateAddressCommand;
use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;

final class AddressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HdSeedRepository::class, EloquentHdSeedRepository::class);
        $this->app->singleton(AddressRepository::class, EloquentAddressRepository::class);

        $this->app->singleton(AddressDirectory::class, function ($app): AddressDirectory {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');

            $driver = (string) $config->get('database.redis.client', 'phpredis');
            if ($this->app->environment('testing') || $driver === 'null') {
                return new InMemoryAddressDirectory();
            }

            return new RedisAddressDirectory(
                redis: $app->make(RedisFactory::class),
                connection: (string) $config->get('block_ingestion.directory.redis_connection', 'default'),
                keyPrefix: (string) $config->get('block_ingestion.directory.key_prefix', 'watched'),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(AddressGenerated::class, RegisterAddressInDirectory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateHdSeedCommand::class,
                GenerateAddressCommand::class,
            ]);
        }
    }
}
