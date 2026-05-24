<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Address;

use App\Modules\Address\Domain\Entity\Address;
use App\Modules\Address\Domain\Entity\HdSeed;
use App\Modules\Address\Domain\Repository\AddressRepository;
use App\Modules\Address\Domain\Repository\HdSeedRepository;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\DerivationPath;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\HdSeedReference;
use App\Modules\Network\Domain\ValueObject\Address as AddressVO;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;
use Illuminate\Support\Str;

it('allocates monotonic derivation indexes per (seed, family)', function (): void {
    /** @var HdSeedRepository $seeds */
    $seeds = app(HdSeedRepository::class);
    /** @var AddressRepository $addresses */
    $addresses = app(AddressRepository::class);

    $seedId = new HdSeedId((string) Str::uuid());
    $seeds->save(HdSeed::create(
        $seedId,
        new HdSeedReference('seed-roundtrip'),
        null,
        new DateTimeImmutable(),
    ));

    expect($addresses->nextDerivationIndex($seedId, ChainFamily::Evm)->value)->toBe(0);

    // Persist first address.
    $addresses->save(Address::generate(
        id: new AddressId((string) Str::uuid()),
        seedId: $seedId,
        family: ChainFamily::Evm,
        address: new AddressVO('0x0000000000000000000000000000000000000001'),
        derivationPath: new DerivationPath("m/44'/60'/0'/0/0"),
        walletId: null,
        now: new DateTimeImmutable(),
    ));

    expect($addresses->nextDerivationIndex($seedId, ChainFamily::Evm)->value)->toBe(1);

    // Different family uses an independent counter.
    expect($addresses->nextDerivationIndex($seedId, ChainFamily::Bitcoin)->value)->toBe(0);
});

it('round-trips an Address aggregate', function (): void {
    /** @var HdSeedRepository $seeds */
    $seeds = app(HdSeedRepository::class);
    /** @var AddressRepository $addresses */
    $addresses = app(AddressRepository::class);

    $seedId = new HdSeedId((string) Str::uuid());
    $seeds->save(HdSeed::create(
        $seedId,
        new HdSeedReference('seed-roundtrip-2'),
        ChainFamily::Evm,
        new DateTimeImmutable('2026-01-01T00:00:00Z'),
    ));

    $id = new AddressId((string) Str::uuid());
    $addresses->save(Address::generate(
        id: $id,
        seedId: $seedId,
        family: ChainFamily::Evm,
        address: new AddressVO('0x9858EfFD232B4033E47d90003D41EC34EcaEda94'),
        derivationPath: new DerivationPath("m/44'/60'/0'/0/0"),
        walletId: null,
        now: new DateTimeImmutable(),
    ));

    $loaded = $addresses->findById($id);
    expect($loaded)->not->toBeNull();
    expect((string) $loaded->address)->toBe('0x9858EfFD232B4033E47d90003D41EC34EcaEda94');
    expect($loaded->family)->toBe(ChainFamily::Evm);
});
