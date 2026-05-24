<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Exception\SigningClientException;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('sends the raw hex + extras to /v1/tx/sign and wraps the result', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/tx/sign' => Http::response([
            'signed_hex' => '0x02f8b00102',
        ], 200),
    ]);

    /** @var SigningClient $client */
    $client = app(SigningClient::class);
    $signed = $client->signRawTx(
        family: ChainFamily::Evm,
        seedReference: 'seed-evm',
        path: "m/44'/60'/0'/1/0",
        rawHex: 'tx-pending-eth-1',
        extra: ['chain_id' => 11155111, 'nonce' => 1],
    );

    expect($signed->family)->toBe(ChainFamily::Evm);
    expect($signed->hex)->toBe('0x02f8b00102');

    Http::assertSent(function ($req): bool {
        return ($req['family'] ?? null) === 'evm'
            && ($req['path'] ?? null) === "m/44'/60'/0'/1/0"
            && ($req['chain_id'] ?? null) === 11155111;
    });
});

it('translates an HTTP error from signing-svc into SigningClientException', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/tx/sign' => Http::response([
            'error' => ['code' => 'bad_extra', 'message' => 'missing chain_id'],
        ], 400),
    ]);

    /** @var SigningClient $client */
    $client = app(SigningClient::class);
    expect(fn () => $client->signRawTx(
        family: ChainFamily::Evm,
        seedReference: 'seed-evm',
        path: "m/44'/60'/0'/1/0",
        rawHex: 'tx-pending-eth-1',
    ))->toThrow(SigningClientException::class);
});
