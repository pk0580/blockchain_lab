<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Provider;

use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use App\Modules\ReorgDetection\Domain\Event\ReorgTooDeep;
use App\Modules\Withdrawal\Application\Contract\HotWalletResolver;
use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use App\Modules\Withdrawal\Application\Contract\WithdrawalIdGenerator;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use App\Modules\Withdrawal\Domain\Contract\NonceAllocator;
use App\Modules\Withdrawal\Domain\Contract\NonceProbe;
use App\Modules\Withdrawal\Domain\Contract\NonceProbeRegistry;
use App\Modules\Withdrawal\Domain\Contract\TxBuilder;
use App\Modules\Withdrawal\Domain\Contract\TxBuilderRegistry;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookupRegistry;
use App\Modules\Withdrawal\Domain\Event\WithdrawalStuck;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Infrastructure\Confirmation\BitcoinWithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Infrastructure\Confirmation\ConfigurableWithdrawalConfirmationLookupRegistry;
use App\Modules\Withdrawal\Infrastructure\Confirmation\EvmWithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Infrastructure\HotWallet\ConfigHotWalletResolver;
use App\Modules\Withdrawal\Infrastructure\Listener\PauseChainOnReorgTooDeep;
use App\Modules\Withdrawal\Infrastructure\Listener\ReplaceOnWithdrawalStuck;
use App\Modules\Withdrawal\Infrastructure\Nonce\ConfigurableNonceProbeRegistry;
use App\Modules\Withdrawal\Infrastructure\Nonce\EloquentNonceAllocator;
use App\Modules\Withdrawal\Infrastructure\Nonce\EvmNonceProbe;
use App\Modules\Withdrawal\Infrastructure\Pause\CacheChainPauseRegistry;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Mappers\WithdrawalMapper;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Repositories\EloquentWithdrawalRepository;
use App\Modules\Withdrawal\Infrastructure\Support\LaravelEventDispatcher;
use App\Modules\Withdrawal\Infrastructure\Support\UuidWithdrawalIdGenerator;
use App\Modules\Withdrawal\Infrastructure\TxBuilder\BitcoinTxBuilder;
use App\Modules\Withdrawal\Infrastructure\TxBuilder\ConfigurableTxBuilderRegistry;
use App\Modules\Withdrawal\Infrastructure\TxBuilder\EvmTxBuilder;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class WithdrawalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NonceProbeRegistry::class, function ($app): NonceProbeRegistry {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            $http = $app->make(HttpFactory::class);

            /** @var array<string, NonceProbe> $probes */
            $probes = [
                ChainFamily::Evm->value => new EvmNonceProbe(
                    http: $http,
                    timeoutSeconds: (int) $config->get('withdrawal.nonce.timeout_seconds', 5),
                    connectTimeoutSeconds: (int) $config->get('withdrawal.nonce.connect_timeout_seconds', 2),
                    retries: (int) $config->get('withdrawal.nonce.retries', 1),
                    retryBackoffMs: (int) $config->get('withdrawal.nonce.retry_backoff_ms', 150),
                ),
            ];

            return new ConfigurableNonceProbeRegistry($probes);
        });

        $this->app->singleton(NonceAllocator::class, function ($app): NonceAllocator {
            return new EloquentNonceAllocator(
                db: $app->make(DatabaseManager::class),
                probes: $app->make(NonceProbeRegistry::class),
            );
        });

        $this->app->singleton(WithdrawalRepository::class, function (): WithdrawalRepository {
            return new EloquentWithdrawalRepository(new WithdrawalMapper());
        });

        $this->app->singleton(TxBuilderRegistry::class, function ($app): TxBuilderRegistry {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');

            $btcBuilder = new BitcoinTxBuilder(
                rpcFactory: function (Chain $chain) use ($app, $config): BitcoinRpcClient {
                    return $this->buildBitcoinRpc($app, $config, $chain);
                },
            );

            $evmBuilder = new EvmTxBuilder(
                rpc: $app->make(EvmJsonRpc::class),
                endpoints: $app->make(RpcEndpointPicker::class),
            );

            /** @var array<string, TxBuilder> $byFamily */
            $byFamily = [
                ChainFamily::Bitcoin->value => $btcBuilder,
                ChainFamily::Evm->value => $evmBuilder,
            ];

            return new ConfigurableTxBuilderRegistry($byFamily);
        });

        $this->app->singleton(WithdrawalConfirmationLookupRegistry::class, function ($app): WithdrawalConfirmationLookupRegistry {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');

            $btcLookup = new BitcoinWithdrawalConfirmationLookup(
                rpcFactory: function (Chain $chain) use ($app, $config): BitcoinRpcClient {
                    return $this->buildBitcoinRpc($app, $config, $chain);
                },
            );

            $evmLookup = new EvmWithdrawalConfirmationLookup(
                rpc: $app->make(EvmJsonRpc::class),
                endpoints: $app->make(RpcEndpointPicker::class),
            );

            /** @var array<string, WithdrawalConfirmationLookup> $byFamily */
            $byFamily = [
                ChainFamily::Bitcoin->value => $btcLookup,
                ChainFamily::Evm->value => $evmLookup,
            ];

            return new ConfigurableWithdrawalConfirmationLookupRegistry($byFamily);
        });

        $this->app->singleton(ChainPauseRegistry::class, function ($app): ChainPauseRegistry {
            return new CacheChainPauseRegistry($app->make(CacheRepository::class));
        });

        $this->app->singleton(HotWalletResolver::class, ConfigHotWalletResolver::class);
        $this->app->singleton(WithdrawalIdGenerator::class, UuidWithdrawalIdGenerator::class);
        $this->app->singleton(WithdrawalEventDispatcher::class, LaravelEventDispatcher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(WithdrawalStuck::class, ReplaceOnWithdrawalStuck::class);
        $events->listen(ReorgTooDeep::class, PauseChainOnReorgTooDeep::class);
    }

    private function buildBitcoinRpc(
        \Illuminate\Contracts\Foundation\Application $app,
        \Illuminate\Contracts\Config\Repository $config,
        Chain $chain,
    ): BitcoinRpcClient {
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
