<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Fee;

use App\Modules\Fee\Domain\Exception\FeeEstimationFailedException;
use App\Modules\Fee\Domain\ValueObject\EvmFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Infrastructure\Estimator\EvmFeeEstimator;
use App\Modules\Fee\Infrastructure\Rpc\EvmRpcClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

function makeEvmChain(string $url = 'https://rpc.sepolia.example'): Chain
{
    return Chain::register(
        id: new ChainId('ethereum-sepolia'),
        name: new ChainName('Ethereum Sepolia'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [new RpcEndpoint($url, RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
}

function makeEvmEstimator(float $multiplier = 2.0): EvmFeeEstimator
{
    /** @var HttpFactory $http */
    $http = app(HttpFactory::class);
    return new EvmFeeEstimator(
        rpc: new EvmRpcClient(
            http: $http,
            timeoutSeconds: 1,
            connectTimeoutSeconds: 1,
            retries: 0,
            retryBackoffMs: 1,
        ),
        priorityPercentiles: ['low' => 10, 'standard' => 50, 'high' => 90],
        baseFeeMultiplier: $multiplier,
        gasLimitTransfer: 21000,
    );
}

it('computes maxFee = baseFee*multiplier + averageReward, gasLimit=21000', function (): void {
    // baseFeePerGas: [10gwei, 12gwei, 14gwei, 15gwei, 18gwei pending]
    // reward[][p50]: [2gwei, 3gwei, 2gwei, 3gwei] → среднее = 2.5 → bc-div → 2 (round down)
    Http::fake([
        'rpc.sepolia.example' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'baseFeePerGas' => ['0x2540be400', '0x2cb417800', '0x342770c00', '0x37e11d600', '0x430e23400'],
                'reward' => [
                    ['0x77359400'],   // 2 gwei
                    ['0xb2d05e00'],   // 3 gwei
                    ['0x77359400'],   // 2 gwei
                    ['0xb2d05e00'],   // 3 gwei
                ],
                'gasUsedRatio' => [0.5, 0.5, 0.5, 0.5],
            ],
        ]),
    ]);

    $quote = makeEvmEstimator(2.0)->estimate(makeEvmChain(), FeePriority::Standard);
    expect($quote->breakdown)->toBeInstanceOf(EvmFeeBreakdown::class);
    /** @var EvmFeeBreakdown $br */
    $br = $quote->breakdown;

    // bcdiv(10000000000, 4) на 2+3+2+3=10gwei → 2 gwei среднее.
    expect($br->maxPriorityFeePerGasWei)->toBe('2500000000');

    // baseFee_next = 18 gwei = 18000000000 wei. multiplier=2 → 36000000000. + priority 2500000000 = 38500000000.
    expect($br->maxFeePerGasWei)->toBe('38500000000');
    expect($br->gasLimit)->toBe(21000);
});

it('falls back to 1 wei when baseFee and rewards are all zero (empty testnet)', function (): void {
    Http::fake([
        'rpc.sepolia.example' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'baseFeePerGas' => ['0x0', '0x0', '0x0', '0x0', '0x0'],
                'reward' => [['0x0'], ['0x0'], ['0x0'], ['0x0']],
                'gasUsedRatio' => [0, 0, 0, 0],
            ],
        ]),
    ]);

    $quote = makeEvmEstimator()->estimate(makeEvmChain(), FeePriority::Low);
    /** @var EvmFeeBreakdown $br */
    $br = $quote->breakdown;

    expect($br->maxFeePerGasWei)->toBe('1');
    expect($br->maxPriorityFeePerGasWei)->toBe('1');
});

it('surfaces RPC-level errors as FeeEstimationFailedException', function (): void {
    Http::fake([
        'rpc.sepolia.example' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32602, 'message' => 'invalid percentile'],
        ]),
    ]);

    makeEvmEstimator()->estimate(makeEvmChain(), FeePriority::High);
})->throws(FeeEstimationFailedException::class);

it('refuses non-evm chains as a sanity guard', function (): void {
    $chain = Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('BTC'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint('http://bitcoin-regtest:18443', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );

    makeEvmEstimator()->estimate($chain, FeePriority::Standard);
})->throws(\RuntimeException::class);
