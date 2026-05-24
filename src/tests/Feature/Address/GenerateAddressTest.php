<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Modules\Address\Application\UseCase\CreateHdSeed\CreateHdSeedAction;
use App\Modules\Address\Application\UseCase\CreateHdSeed\CreateHdSeedData;
use App\Modules\Address\Application\UseCase\GenerateAddress\GenerateAddressAction;
use App\Modules\Address\Application\UseCase\GenerateAddress\GenerateAddressData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('generates ETH addresses with monotonically advancing indexes', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/seeds' => Http::response(['reference' => 'seed-feature-eth', 'created' => true], 201),
        'http://signing-svc:8080/v1/addresses/derive' => Http::sequence()
            ->push(['address' => '0x9858EfFD232B4033E47d90003D41EC34EcaEda94', 'family' => 'evm', 'path' => "m/44'/60'/0'/0/0"], 200)
            ->push(['address' => '0x6Fac4D18c912343BF86fa7049364Dd4E424Ab9C0', 'family' => 'evm', 'path' => "m/44'/60'/0'/0/1"], 200),
    ]);

    $seedId = app(CreateHdSeedAction::class)->handle(
        new CreateHdSeedData(reference: 'seed-feature-eth', family: 'evm'),
    );

    $a0 = app(GenerateAddressAction::class)->handle(new GenerateAddressData($seedId->value, 'evm'));
    $a1 = app(GenerateAddressAction::class)->handle(new GenerateAddressData($seedId->value, 'evm'));

    expect($a0->derivationPath)->toBe("m/44'/60'/0'/0/0");
    expect($a1->derivationPath)->toBe("m/44'/60'/0'/0/1");
    expect($a0->address)->toBe('0x9858EfFD232B4033E47d90003D41EC34EcaEda94');
    expect($a1->address)->toBe('0x6Fac4D18c912343BF86fa7049364Dd4E424Ab9C0');
});

it('refuses to generate when the seed family does not match', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/seeds' => Http::response(['reference' => 'seed-feature-mismatch', 'created' => true], 201),
    ]);

    $seedId = app(CreateHdSeedAction::class)->handle(
        new CreateHdSeedData(reference: 'seed-feature-mismatch', family: 'evm'),
    );

    expect(fn () => app(GenerateAddressAction::class)
        ->handle(new GenerateAddressData($seedId->value, 'bitcoin')))
        ->toThrow(\DomainException::class);
});

it('rejects an unknown HdSeed id', function (): void {
    expect(fn () => app(GenerateAddressAction::class)
        ->handle(new GenerateAddressData('11111111-2222-3333-4444-555555555555', 'evm')))
        ->toThrow(\App\Modules\Address\Domain\Exception\HdSeedNotFoundException::class);
});
