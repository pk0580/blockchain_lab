<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Provider;

use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\ChainAdapterRegistry;
use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Infrastructure\Adapter\BitcoinAdapter;
use App\Modules\Network\Infrastructure\Adapter\EvmAdapter;
use App\Modules\Network\Infrastructure\Adapter\NoOpChainAdapter;
use App\Modules\Network\Infrastructure\Persistence\Eloquent\Repositories\EloquentChainRepository;
use App\Modules\Network\Infrastructure\Picker\FirstHttpEndpointPicker;
use App\Modules\Network\Infrastructure\Registry\ConfigurableChainAdapterRegistry;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use App\Modules\Network\Infrastructure\Signer\HttpSigningClient;
use App\Modules\Network\UI\Console\ChainListCommand;
use App\Modules\Network\UI\Console\RegisterChainCommand;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class NetworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChainRepository::class, EloquentChainRepository::class);

        // Picker по умолчанию: первый эндпоинт нужного типа (kind). NodeHealthServiceProvider
        // переопределяет эту привязку на HealthBasedRpcEndpointPicker (если модуль подключён).
        $this->app->singleton(RpcEndpointPicker::class, FirstHttpEndpointPicker::class);

        $this->app->singleton(SigningClient::class, function ($app): SigningClient {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            $token = (string) $config->get('network.signing.bearer_token', '');
            if ($token === '') {
                throw new RuntimeException('network.signing.bearer_token не настроен.');
            }
            return new HttpSigningClient(
                http: $app->make(HttpFactory::class),
                baseUrl: (string) $config->get('network.signing.url'),
                bearerToken: $token,
                timeoutSeconds: (int) $config->get('network.signing.timeout_seconds', 5),
                connectTimeoutSeconds: (int) $config->get('network.signing.connect_timeout_seconds', 2),
                retries: (int) $config->get('network.signing.retries', 2),
                retryBackoffMs: (int) $config->get('network.signing.retry_backoff_ms', 150),
            );
        });

        $this->app->singleton(EvmJsonRpc::class, function ($app): EvmJsonRpc {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            return new EvmJsonRpc(
                http: $app->make(HttpFactory::class),
                timeoutSeconds: (int) $config->get('network.evm.timeout_seconds', 5),
                connectTimeoutSeconds: (int) $config->get('network.evm.connect_timeout_seconds', 2),
                retries: (int) $config->get('network.evm.retries', 1),
                retryBackoffMs: (int) $config->get('network.evm.retry_backoff_ms', 150),
            );
        });

        $this->app->singleton(ChainAdapterRegistry::class, function ($app): ChainAdapterRegistry {
            $registry = new ConfigurableChainAdapterRegistry($app->make(ChainRepository::class));

            $registry->registerFamily(
                ChainFamily::Bitcoin,
                fn (Chain $chain) => new BitcoinAdapter(
                    chain: $chain,
                    blockSource: $app->make(BlockSourceFactory::class)->for($chain),
                    signing: $app->make(SigningClient::class),
                    rpc: $this->buildBitcoinRpc($app, $chain),
                ),
            );

            $registry->registerFamily(
                ChainFamily::Evm,
                fn (Chain $chain): ChainAdapter => new EvmAdapter(
                    chain: $chain,
                    rpc: $app->make(EvmJsonRpc::class),
                    signing: $app->make(SigningClient::class),
                    endpoints: $app->make(RpcEndpointPicker::class),
                ),
            );

            // Tron остаётся на NoOp до Phase 6.5+.
            $registry->registerFamily(
                ChainFamily::Tron,
                fn (Chain $chain): ChainAdapter => new NoOpChainAdapter($chain),
            );

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterChainCommand::class,
                ChainListCommand::class,
            ]);
        }
    }

    private function buildBitcoinRpc(\Illuminate\Contracts\Foundation\Application $app, Chain $chain): BitcoinRpcClient
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app->make('config');
        /** @var RpcEndpointPicker $picker */
        $picker = $app->make(RpcEndpointPicker::class);
        $endpoint = $picker->pick($chain);

        return new BitcoinRpcClient(
            http: $app->make(HttpFactory::class),
            url: $endpoint->url,
            user: (string) $config->get('block_ingestion.bitcoin.rpc_user', ''),
            password: (string) $config->get('block_ingestion.bitcoin.rpc_password', ''),
            timeoutSeconds: (int) $config->get('block_ingestion.bitcoin.timeout_seconds', 5),
            connectTimeoutSeconds: (int) $config->get('block_ingestion.bitcoin.connect_timeout_seconds', 2),
            retries: (int) $config->get('block_ingestion.bitcoin.retries', 1),
            retryBackoffMs: (int) $config->get('block_ingestion.bitcoin.retry_backoff_ms', 150),
        );
    }
}
