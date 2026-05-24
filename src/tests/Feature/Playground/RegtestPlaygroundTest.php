<?php

declare(strict_types=1);

namespace Tests\Feature\Playground;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['education.regtest.url' => 'http://bitcoin-regtest.test:18443']);
    config(['education.regtest.user' => 'bitcoin']);
    config(['education.regtest.password' => 'bitcoin']);
});

function rpcResponse(mixed $result, ?array $error = null): array
{
    return ['result' => $result, 'error' => $error, 'id' => 'x'];
}

it('GET /api/playground/regtest/mempool returns mempool list + height', function (): void {
    Http::fake([
        'http://bitcoin-regtest.test:18443*' => Http::sequence()
            ->push(rpcResponse(['txid_a', 'txid_b']))
            ->push(rpcResponse(42)),
    ]);

    $this->getJson('/api/playground/regtest/mempool')
        ->assertOk()
        ->assertJsonPath('data.block_count', 42)
        ->assertJsonPath('data.mempool_size', 2)
        ->assertJsonPath('data.txids', ['txid_a', 'txid_b']);
});

it('GET /api/playground/regtest/state returns recent blocks', function (): void {
    Http::fake([
        'http://bitcoin-regtest.test:18443*' => Http::sequence()
            ->push(rpcResponse('hash_3'))            // getbestblockhash
            ->push(rpcResponse(3))                   // getblockcount
            ->push(rpcResponse([                     // getblock hash_3
                'height' => 3,
                'previousblockhash' => 'hash_2',
            ]))
            ->push(rpcResponse([
                'height' => 2,
                'previousblockhash' => 'hash_1',
            ]))
            ->push(rpcResponse([
                'height' => 1,
                'previousblockhash' => '',
            ])),
    ]);

    $this->getJson('/api/playground/regtest/state')
        ->assertOk()
        ->assertJsonPath('data.tip_hash', 'hash_3')
        ->assertJsonPath('data.block_count', 3)
        ->assertJsonPath('data.recent.0.hash', 'hash_3')
        ->assertJsonPath('data.recent.2.parent_hash', '');
});

it('POST /api/playground/regtest/mine generates blocks', function (): void {
    Http::fake([
        'http://bitcoin-regtest.test:18443*' => Http::sequence()
            ->push(rpcResponse('bcrt1qfake'))            // getnewaddress
            ->push(rpcResponse(['h1', 'h2'])),           // generatetoaddress
    ]);

    $this->postJson('/api/playground/regtest/mine', ['blocks' => 2])
        ->assertOk()
        ->assertJsonPath('data.mined', 2)
        ->assertJsonPath('data.address', 'bcrt1qfake');
});

it('mine validates block count bounds', function (): void {
    Http::fake();

    $this->postJson('/api/playground/regtest/mine', ['blocks' => 999])
        ->assertUnprocessable();
});

it('POST /api/playground/regtest/invalidate-tip drops current best', function (): void {
    Http::fake([
        'http://bitcoin-regtest.test:18443*' => Http::sequence()
            ->push(rpcResponse('old_tip'))           // getbestblockhash (before)
            ->push(rpcResponse(null))                // invalidateblock
            ->push(rpcResponse('new_tip'))           // getbestblockhash (after)
            ->push(rpcResponse(41)),                 // getblockcount
    ]);

    $this->postJson('/api/playground/regtest/invalidate-tip', [])
        ->assertOk()
        ->assertJsonPath('data.invalidated_hash', 'old_tip')
        ->assertJsonPath('data.new_tip_hash', 'new_tip')
        ->assertJsonPath('data.new_block_count', 41);
});

it('returns 502 when regtest is unreachable', function (): void {
    Http::fake([
        '*' => Http::response('not json', 500),
    ]);

    $this->getJson('/api/playground/regtest/mempool')
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('error.code', 'regtest_unavailable');
});
