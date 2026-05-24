<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Modules\BlockIngestion\Domain\Contract\BlockSource;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\BroadcastFailedException;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Infrastructure\Adapter\BitcoinAdapter;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

final class StubBlockSourceForBroadcast implements BlockSource
{
    public function currentHead(): BlockHeight
    {
        return new BlockHeight(0);
    }

    public function fetchBlockAt(BlockHeight $height): \App\Modules\BlockIngestion\Domain\ReadModel\FetchedBlock
    {
        throw new \RuntimeException('not needed in this test');
    }
}

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function makeBitcoinChainForBroadcast(): Chain
{
    return Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('Bitcoin Regtest'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint('http://bitcoin-regtest:18443', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
}

it('sends sendrawtransaction with the signed hex and returns the resulting txid', function (): void {
    Http::fake([
        'http://bitcoin-regtest:18443' => Http::response([
            'result' => str_repeat('b', 64),
            'error' => null,
            'id' => 'sendrawtransaction',
        ], 200),
    ]);

    /** @var SigningClient $signing */
    $signing = app(SigningClient::class);
    $rpc = new BitcoinRpcClient(
        http: app(HttpFactory::class),
        url: 'http://bitcoin-regtest:18443',
        user: 'bitcoin',
        password: 'secret',
    );

    $adapter = new BitcoinAdapter(
        chain: makeBitcoinChainForBroadcast(),
        blockSource: new StubBlockSourceForBroadcast(),
        signing: $signing,
        rpc: $rpc,
    );

    $tx = new SignedRawTx(ChainFamily::Bitcoin, '0100abcd');
    $hash = $adapter->broadcast($tx);

    expect($hash->value)->toBe(str_repeat('b', 64));
    Http::assertSent(function ($req): bool {
        $payload = json_decode($req->body(), true);
        return is_array($payload)
            && ($payload['method'] ?? null) === 'sendrawtransaction'
            && ($payload['params'][0] ?? null) === '0100abcd';
    });
});

it('maps a node-level error to BroadcastFailedException::transport via BlockSourceException', function (): void {
    Http::fake([
        'http://bitcoin-regtest:18443' => Http::response([
            'result' => null,
            'error' => ['code' => -26, 'message' => 'min relay fee not met'],
            'id' => 'sendrawtransaction',
        ], 200),
    ]);

    /** @var SigningClient $signing */
    $signing = app(SigningClient::class);
    $rpc = new BitcoinRpcClient(
        http: app(HttpFactory::class),
        url: 'http://bitcoin-regtest:18443',
        user: 'bitcoin',
        password: 'secret',
    );

    $adapter = new BitcoinAdapter(
        chain: makeBitcoinChainForBroadcast(),
        blockSource: new StubBlockSourceForBroadcast(),
        signing: $signing,
        rpc: $rpc,
    );

    $tx = new SignedRawTx(ChainFamily::Bitcoin, '0100abcd');
    expect(fn () => $adapter->broadcast($tx))->toThrow(BroadcastFailedException::class);
});

it('refuses to broadcast an EVM-shaped tx', function (): void {
    /** @var SigningClient $signing */
    $signing = app(SigningClient::class);
    $rpc = new BitcoinRpcClient(
        http: app(HttpFactory::class),
        url: 'http://bitcoin-regtest:18443',
        user: 'bitcoin',
        password: 'secret',
    );
    $adapter = new BitcoinAdapter(
        chain: makeBitcoinChainForBroadcast(),
        blockSource: new StubBlockSourceForBroadcast(),
        signing: $signing,
        rpc: $rpc,
    );

    $tx = new SignedRawTx(ChainFamily::Evm, '0x02f8b0');
    expect(fn () => $adapter->broadcast($tx))->toThrow(BroadcastFailedException::class);
});
