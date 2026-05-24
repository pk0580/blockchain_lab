<?php

declare(strict_types=1);

namespace Tests\Feature\Playground;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['network.signing.url' => 'http://signing-svc.test:8080']);
    config(['network.signing.bearer_token' => 'test-token']);
});

it('proxies POST /api/playground/keypair to signing-svc', function (): void {
    Http::fake([
        'http://signing-svc.test:8080/v1/playground/keypair' => Http::response([
            'mnemonic' => 'abandon abandon abandon ...',
            'derivation_path' => "m/44'/0'/0'/0/0",
            'private_key_hex' => str_repeat('a', 64),
            'public_key_compressed_hex' => '02'.str_repeat('b', 64),
            'public_key_uncompressed_hex' => '04'.str_repeat('b', 128),
            'bitcoin_address' => 'bc1qfakefakefake',
            'ethereum_address' => '0xFakeAddress',
            'tron_address' => 'TFakeAddress',
        ], 200),
    ]);

    $response = $this->postJson('/api/playground/keypair', []);

    $response->assertOk()
        ->assertJsonPath('data.bitcoin_address', 'bc1qfakefakefake')
        ->assertJsonPath('data.private_key_hex', str_repeat('a', 64));

    Http::assertSent(fn ($req) => $req->url() === 'http://signing-svc.test:8080/v1/playground/keypair'
        && $req->hasHeader('Authorization', 'Bearer test-token'));
});

it('sends empty keypair body as JSON object `{}`, not JSON array `[]`', function (): void {
    // Regression: Go signing-svc unmarshal'ит body в struct и падает на
    // `[]` с "cannot unmarshal array into Go value of type playgroundKeypairReq".
    Http::fake([
        '*' => Http::response(['mnemonic' => 'abandon ...', 'bitcoin_address' => 'bc1qx'], 200),
    ]);

    $this->postJson('/api/playground/keypair', [])->assertOk();

    Http::assertSent(fn ($req) => $req->body() === '{}');
});

it('passes mnemonic param through to signing-svc', function (): void {
    Http::fake([
        '*' => Http::response(['mnemonic' => 'abandon ...', 'bitcoin_address' => 'bc1qx'], 200),
    ]);

    $this->postJson('/api/playground/keypair', ['mnemonic' => 'abandon abandon abandon ...'])
        ->assertOk();

    Http::assertSent(fn ($req) => $req['mnemonic'] === 'abandon abandon abandon ...');
});

it('returns 502 when signing-svc fails', function (): void {
    Http::fake([
        '*' => Http::response([
            'error' => ['code' => 'keypair_failed', 'message' => 'boom'],
        ], 500),
    ]);

    $this->postJson('/api/playground/keypair', [])
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('error.code', 'keypair_failed');
});

it('signs a message via /api/playground/sign', function (): void {
    Http::fake([
        '*' => Http::response([
            'digest_hex' => str_repeat('1', 64),
            'r_hex' => str_repeat('2', 64),
            's_hex' => str_repeat('3', 64),
            'signature_der_hex' => '30...',
            'algorithm' => 'ECDSA',
        ], 200),
    ]);

    $response = $this->postJson('/api/playground/sign', [
        'private_key_hex' => str_repeat('a', 64),
        'message_hex' => '48656c6c6f',
    ]);

    $response->assertOk()->assertJsonPath('data.algorithm', 'ECDSA');
});

it('rejects bad private key shape on /sign', function (): void {
    Http::fake();

    $this->postJson('/api/playground/sign', [
        'private_key_hex' => 'too-short',
        'message_hex' => '00',
    ])->assertUnprocessable();

    Http::assertNothingSent();
});

it('decodes a bitcoin tx via /api/playground/decode', function (): void {
    Http::fake([
        '*' => Http::response([
            'version' => 1,
            'locktime' => 0,
            'inputs' => [],
            'outputs' => [],
            'txid_hex' => str_repeat('f', 64),
            'size_bytes' => 100,
        ], 200),
    ]);

    $response = $this->postJson('/api/playground/decode', [
        'chain' => 'bitcoin',
        'raw_hex' => '01000000',
    ]);

    $response->assertOk()->assertJsonPath('data.version', 1);
});

it('rejects unsupported chains on /decode', function (): void {
    Http::fake();

    $this->postJson('/api/playground/decode', [
        'chain' => 'dogecoin',
        'raw_hex' => '00',
    ])->assertUnprocessable();

    Http::assertNothingSent();
});
