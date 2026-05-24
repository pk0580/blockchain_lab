<?php

declare(strict_types=1);

namespace App\Modules\Fee\Infrastructure\Provider;

use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Contract\FeeEstimatorRegistry;
use App\Modules\Fee\Infrastructure\Estimator\BitcoinFeeEstimator;
use App\Modules\Fee\Infrastructure\Estimator\EvmFeeEstimator;
use App\Modules\Fee\Infrastructure\Estimator\StubFeeEstimator;
use App\Modules\Fee\Infrastructure\Registry\ConfigurableFeeEstimatorRegistry;
use App\Modules\Fee\Infrastructure\Rpc\BitcoinFeeRpcClient;
use App\Modules\Fee\Infrastructure\Rpc\EvmRpcClient;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class FeeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeeEstimatorRegistry::class, function ($app): FeeEstimatorRegistry {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            $http = $app->make(HttpFactory::class);

            /** @var array{low: int, standard: int, high: int} $btcTargets */
            $btcTargets = $config->get('fee.bitcoin.targets', ['low' => 6, 'standard' => 3, 'high' => 1]);
            /** @var array{low: string, standard: string, high: string} $btcModes */
            $btcModes = $config->get('fee.bitcoin.modes', ['low' => 'ECONOMICAL', 'standard' => 'ECONOMICAL', 'high' => 'CONSERVATIVE']);

            $bitcoin = new BitcoinFeeEstimator(
                rpc: new BitcoinFeeRpcClient(
                    http: $http,
                    user: (string) $config->get('block_ingestion.bitcoin.rpc_user', ''),
                    password: (string) $config->get('block_ingestion.bitcoin.rpc_password', ''),
                    timeoutSeconds: (int) $config->get('block_ingestion.bitcoin.timeout_seconds', 5),
                    connectTimeoutSeconds: (int) $config->get('block_ingestion.bitcoin.connect_timeout_seconds', 2),
                    retries: (int) $config->get('block_ingestion.bitcoin.retries', 1),
                    retryBackoffMs: (int) $config->get('block_ingestion.bitcoin.retry_backoff_ms', 150),
                ),
                targets: $btcTargets,
                modes: $btcModes,
                minSatPerVbyte: (int) $config->get('fee.bitcoin.min_sat_per_vbyte', 1),
            );

            /** @var array{low: int, standard: int, high: int} $evmPercentiles */
            $evmPercentiles = $config->get('fee.evm.priority_percentiles', ['low' => 10, 'standard' => 50, 'high' => 90]);

            $evm = new EvmFeeEstimator(
                rpc: new EvmRpcClient(
                    http: $http,
                    timeoutSeconds: (int) $config->get('fee.evm.timeout_seconds', 5),
                    connectTimeoutSeconds: (int) $config->get('fee.evm.connect_timeout_seconds', 2),
                    retries: (int) $config->get('fee.evm.retries', 1),
                    retryBackoffMs: (int) $config->get('fee.evm.retry_backoff_ms', 150),
                ),
                priorityPercentiles: $evmPercentiles,
                baseFeeMultiplier: (float) $config->get('fee.evm.base_fee_multiplier', 2.0),
                gasLimitTransfer: (int) $config->get('fee.evm.gas_limit_transfer', 21000),
            );

            /** @var array<string, FeeEstimator> $estimators */
            $estimators = [
                ChainFamily::Bitcoin->value => $bitcoin,
                ChainFamily::Evm->value => $evm,
                ChainFamily::Tron->value => new StubFeeEstimator(),
            ];

            return new ConfigurableFeeEstimatorRegistry($estimators);
        });
    }

    public function boot(): void
    {
        // Конфиг подхватывается Laravel автоматически из src/config/fee.php.
    }
}
