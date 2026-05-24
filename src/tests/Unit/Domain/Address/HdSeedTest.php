<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Address;

use App\Modules\Address\Domain\Entity\HdSeed;
use App\Modules\Address\Domain\Event\HdSeedCreated;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\HdSeedReference;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

it('emits HdSeedCreated on creation', function (): void {
    $seed = HdSeed::create(
        new HdSeedId('11111111-2222-3333-4444-555555555555'),
        new HdSeedReference('seed-1'),
        null,
        new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );

    $events = $seed->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(HdSeedCreated::class);
});

it('supports any family when the seed is multi-family (null)', function (): void {
    $seed = HdSeed::create(
        new HdSeedId('11111111-2222-3333-4444-555555555555'),
        new HdSeedReference('seed-1'),
        null,
        new DateTimeImmutable(),
    );
    expect($seed->supportsFamily(ChainFamily::Bitcoin))->toBeTrue();
    expect($seed->supportsFamily(ChainFamily::Evm))->toBeTrue();
    expect($seed->supportsFamily(ChainFamily::Tron))->toBeTrue();
});

it('restricts pinned family seeds to that family only', function (): void {
    $seed = HdSeed::create(
        new HdSeedId('11111111-2222-3333-4444-555555555555'),
        new HdSeedReference('seed-1'),
        ChainFamily::Evm,
        new DateTimeImmutable(),
    );
    expect($seed->supportsFamily(ChainFamily::Evm))->toBeTrue();
    expect($seed->supportsFamily(ChainFamily::Bitcoin))->toBeFalse();
});
