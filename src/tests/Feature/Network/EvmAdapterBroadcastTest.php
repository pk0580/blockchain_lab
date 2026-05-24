<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\BroadcastFailedException;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Infrastructure\Adapter\EvmAdapter;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function makeEvmChainForBroadcast(): Chain
{
    return Chain::register(
        id: new ChainId('ethereum-sepolia'),
        name: new ChainName('Sepolia'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [new RpcEndpoint('https://rpc.sepolia.example', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
}

function makeEvmAdapter(): EvmAdapter
{
    /** @var SigningClient $signing */
    $signing = app(SigningClient::class);
    /** @var \App\Modules\Network\Domain\Contract\RpcEndpointPicker $picker */
    $picker = app(\App\Modules\Network\Domain\Contract\RpcEndpointPicker::class);
    return new EvmAdapter(
        chain: makeEvmChainForBroadcast(),
        rpc: new EvmJsonRpc(http: app(HttpFactory::class)),
        signing: $signing,
        endpoints: $picker,
    );
}

it('returns the txid from eth_sendRawTransaction on success', function (): void {
    Http::fake([
        'https://rpc.sepolia.example' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => '0x'.str_repeat('c', 64),
        ], 200),
    ]);

    $tx = new SignedRawTx(ChainFamily::Evm, '0x02f8b0');
    $hash = makeEvmAdapter()->broadcast($tx);

    expect($hash->value)->toBe('0x'.str_repeat('c', 64));

    Http::assertSent(function ($req): bool {
        $payload = json_decode($req->body(), true);
        return is_array($payload)
            && ($payload['method'] ?? null) === 'eth_sendRawTransaction'
            && ($payload['params'][0] ?? null) === '0x02f8b0';
    });
});

it('maps an RPC error into BroadcastFailedException with rpcCode', function (): void {
    Http::fake([
        'https://rpc.sepolia.example' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => ['code' => -32000, 'message' => 'already known'],
        ], 200),
    ]);

    $tx = new SignedRawTx(ChainFamily::Evm, '0x02f8b0');
    try {
        makeEvmAdapter()->broadcast($tx);
        expect(true)->toBeFalse('should have thrown');
    } catch (BroadcastFailedException $e) {
        expect($e->rpcCode)->toBe('-32000');
    }
});

it('returns current head via eth_blockNumber', function (): void {
    Http::fake([
        'https://rpc.sepolia.example' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => '0x10',
        ], 200),
    ]);

    expect(makeEvmAdapter()->currentHead()->value)->toBe(16);
});

it('refuses to broadcast a Bitcoin tx', function (): void {
    $tx = new SignedRawTx(ChainFamily::Bitcoin, '0100abcd');
    expect(fn () => makeEvmAdapter()->broadcast($tx))
        ->toThrow(BroadcastFailedException::class);
});
