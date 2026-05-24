<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Fee;

use App\Modules\Fee\Domain\Exception\FeeEstimationFailedException;
use App\Modules\Fee\Domain\ValueObject\BitcoinFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Infrastructure\Estimator\BitcoinFeeEstimator;
use App\Modules\Fee\Infrastructure\Rpc\BitcoinFeeRpcClient;
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

function makeBitcoinChain(string $url = 'http://bitcoin-regtest:18443'): Chain
{
    return Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('Bitcoin Regtest'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint($url, RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
}

function makeBitcoinEstimator(): BitcoinFeeEstimator
{
    /** @var HttpFactory $http */
    $http = app(HttpFactory::class);
    return new BitcoinFeeEstimator(
        rpc: new BitcoinFeeRpcClient(
            http: $http,
            user: 'user',
            password: 'pass',
            timeoutSeconds: 1,
            connectTimeoutSeconds: 1,
            retries: 0,
            retryBackoffMs: 1,
        ),
        targets: ['low' => 6, 'standard' => 3, 'high' => 1],
        modes: ['low' => 'ECONOMICAL', 'standard' => 'ECONOMICAL', 'high' => 'CONSERVATIVE'],
        minSatPerVbyte: 1,
    );
}

it('converts BTC/kB to ceil(sat/vbyte) and returns a BitcoinFeeBreakdown', function (): void {
    Http::fake([
        'bitcoin-regtest:18443*' => Http::response([
            'result' => ['feerate' => 0.00012345, 'blocks' => 3],
            'error' => null,
            'id' => 'estimatesmartfee',
        ]),
    ]);

    $quote = makeBitcoinEstimator()->estimate(makeBitcoinChain(), FeePriority::Standard);

    // 0.00012345 BTC/kB = 12345 sat/kB = 12.345 sat/vbyte → ceil = 13
    expect($quote->breakdown)->toBeInstanceOf(BitcoinFeeBreakdown::class);
    /** @var BitcoinFeeBreakdown $br */
    $br = $quote->breakdown;
    expect($br->satPerVbyte)->toBe(13);
    expect($quote->priority)->toBe(FeePriority::Standard);
});

it('falls back to min_sat_per_vbyte when bitcoind has no estimate (feerate = -1)', function (): void {
    Http::fake([
        'bitcoin-regtest:18443*' => Http::response([
            'result' => ['feerate' => -1, 'errors' => ['Insufficient data']],
            'error' => null,
            'id' => 'estimatesmartfee',
        ]),
    ]);

    $quote = makeBitcoinEstimator()->estimate(makeBitcoinChain(), FeePriority::Low);
    /** @var BitcoinFeeBreakdown $br */
    $br = $quote->breakdown;
    expect($br->satPerVbyte)->toBe(1);
});

it('rounds clean divisions without bumping (1 sat/vbyte exactly)', function (): void {
    Http::fake([
        // 0.00001000 BTC/kB = 1000 sat/kB = 1 sat/vB ровно.
        'bitcoin-regtest:18443*' => Http::response([
            'result' => ['feerate' => 0.00001, 'blocks' => 6],
            'error' => null,
            'id' => 'estimatesmartfee',
        ]),
    ]);

    $quote = makeBitcoinEstimator()->estimate(makeBitcoinChain(), FeePriority::Low);
    /** @var BitcoinFeeBreakdown $br */
    $br = $quote->breakdown;
    expect($br->satPerVbyte)->toBe(1);
});

it('surfaces RPC-level errors as FeeEstimationFailedException', function (): void {
    Http::fake([
        'bitcoin-regtest:18443*' => Http::response([
            'result' => null,
            'error' => ['code' => -32601, 'message' => 'Method not found'],
            'id' => 'estimatesmartfee',
        ]),
    ]);

    makeBitcoinEstimator()->estimate(makeBitcoinChain(), FeePriority::High);
})->throws(FeeEstimationFailedException::class);

it('refuses non-bitcoin chains as a sanity guard', function (): void {
    $chain = Chain::register(
        id: new ChainId('ethereum-sepolia'),
        name: new ChainName('Sepolia'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [new RpcEndpoint('https://rpc.sepolia.org', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );

    makeBitcoinEstimator()->estimate($chain, FeePriority::Standard);
})->throws(\RuntimeException::class);
