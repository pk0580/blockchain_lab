<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Address;

use App\Modules\Address\Domain\Entity\Address;
use App\Modules\Address\Domain\Event\AddressGenerated;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\DerivationPath;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Network\Domain\ValueObject\Address as AddressVO;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

it('emits AddressGenerated event on generation', function (): void {
    $addr = Address::generate(
        id: new AddressId('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
        seedId: new HdSeedId('11111111-2222-3333-4444-555555555555'),
        family: ChainFamily::Evm,
        address: new AddressVO('0x9858EfFD232B4033E47d90003D41EC34EcaEda94'),
        derivationPath: new DerivationPath("m/44'/60'/0'/0/0"),
        walletId: null,
        now: new DateTimeImmutable(),
    );

    $events = $addr->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(AddressGenerated::class);
});

it('reports walletId from the constructor', function (): void {
    $addr = Address::generate(
        id: new AddressId('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
        seedId: new HdSeedId('11111111-2222-3333-4444-555555555555'),
        family: ChainFamily::Evm,
        address: new AddressVO('0x9858EfFD232B4033E47d90003D41EC34EcaEda94'),
        derivationPath: new DerivationPath("m/44'/60'/0'/0/0"),
        walletId: null,
        now: new DateTimeImmutable(),
    );
    expect($addr->walletId())->toBeNull();
});
