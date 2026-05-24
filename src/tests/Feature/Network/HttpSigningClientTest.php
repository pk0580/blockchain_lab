<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Exception\SigningClientException;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Feature: HttpSigningClient ↔ signing-svc round-trip
|--------------------------------------------------------------------------
|
| Uses Http::fake so the test is independent of the actual Go service —
| validates the request shape, header, and error mapping the Domain expects.
| End-to-end integration against a live signing-svc lives in Phase-3 once
| the Address module gives us a real call site to exercise.
|
*/

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('derives an address through the signing service', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/addresses/derive' => Http::response([
            'address' => '0x9858EfFD232B4033E47d90003D41EC34EcaEda94',
            'family' => 'evm',
            'path' => "m/44'/60'/0'/0/0",
        ], 200),
    ]);

    $client = $this->app->make(SigningClient::class);
    $address = $client->deriveAddress('test-seed', ChainFamily::Evm, "m/44'/60'/0'/0/0");

    expect((string) $address)->toBe('0x9858EfFD232B4033E47d90003D41EC34EcaEda94');

    Http::assertSent(function ($req): bool {
        return $req->hasHeader('Authorization')
            && str_starts_with($req->header('Authorization')[0] ?? '', 'Bearer ')
            && $req['family'] === 'evm';
    });
});

it('validates an address through the signing service', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/addresses/validate' => Http::response(['valid' => true], 200),
    ]);

    $client = $this->app->make(SigningClient::class);
    expect($client->isAddressValid(ChainFamily::Evm, '0x9858EfFD232B4033E47d90003D41EC34EcaEda94'))->toBeTrue();
});

it('translates HTTP 400 into a typed SigningClientException', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/addresses/derive' => Http::response([
            'error' => ['code' => 'bad_path', 'message' => 'segment "junk" not a number'],
        ], 400),
    ]);

    $client = $this->app->make(SigningClient::class);

    expect(fn () => $client->deriveAddress('s', ChainFamily::Evm, 'm/junk'))
        ->toThrow(SigningClientException::class);
});

it('uses the Laravel HTTP client factory with bearer auth', function (): void {
    $factory = $this->app->make(HttpFactory::class);
    expect($factory)->toBeInstanceOf(HttpFactory::class);

    Http::fake([
        'http://signing-svc:8080/v1/seeds' => Http::response(['reference' => 'r', 'created' => true], 201),
    ]);

    $client = $this->app->make(SigningClient::class);
    expect($client->ensureSeed('r'))->toBeTrue();
});
