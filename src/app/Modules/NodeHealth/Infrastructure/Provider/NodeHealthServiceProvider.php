<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Provider;

use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use App\Modules\NodeHealth\Application\Contract\NodeHealthEventDispatcher;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbe;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbeRegistry;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Infrastructure\Persistence\CacheEndpointHealthRegistry;
use App\Modules\NodeHealth\Infrastructure\Picker\HealthBasedRpcEndpointPicker;
use App\Modules\NodeHealth\Infrastructure\Probe\BitcoinEndpointHealthProbe;
use App\Modules\NodeHealth\Infrastructure\Probe\ConfigurableEndpointHealthProbeRegistry;
use App\Modules\NodeHealth\Infrastructure\Probe\EvmEndpointHealthProbe;
use App\Modules\NodeHealth\Infrastructure\Support\LaravelEventDispatcher;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class NodeHealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EndpointHealthRegistry::class, function ($app): EndpointHealthRegistry {
            return new CacheEndpointHealthRegistry($app->make(CacheRepository::class));
        });

        $this->app->singleton(EndpointHealthProbeRegistry::class, function ($app): EndpointHealthProbeRegistry {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');

            $btcProbe = new BitcoinEndpointHealthProbe(
                rpcFactory: function (Chain $chain, RpcEndpoint $endpoint) use ($app, $config): BitcoinRpcClient {
                    return new BitcoinRpcClient(
                        http: $app->make(HttpFactory::class),
                        url: $endpoint->url,
                        user: (string) $config->get('block_ingestion.bitcoin.rpc_user', ''),
                        password: (string) $config->get('block_ingestion.bitcoin.rpc_password', ''),
                        timeoutSeconds: (int) $config->get('node_health.bitcoin.timeout_seconds', 3),
                        connectTimeoutSeconds: (int) $config->get('node_health.bitcoin.connect_timeout_seconds', 2),
                        retries: 0,
                        retryBackoffMs: 0,
                    );
                },
            );

            $evmProbe = new EvmEndpointHealthProbe($app->make(EvmJsonRpc::class));

            /** @var array<string, EndpointHealthProbe> $byFamily */
            $byFamily = [
                ChainFamily::Bitcoin->value => $btcProbe,
                ChainFamily::Evm->value => $evmProbe,
            ];

            return new ConfigurableEndpointHealthProbeRegistry($byFamily);
        });

        $this->app->singleton(NodeHealthEventDispatcher::class, LaravelEventDispatcher::class);

        // Переопределяем выборщик (picker) по умолчанию из модуля Network: после регистрации NodeHealth
        // вызов `app(RpcEndpointPicker::class)` будет возвращать реализацию, учитывающую состояние узлов (health-based).
        $this->app->singleton(RpcEndpointPicker::class, function ($app): RpcEndpointPicker {
            return new HealthBasedRpcEndpointPicker($app->make(EndpointHealthRegistry::class));
        });
    }
}
