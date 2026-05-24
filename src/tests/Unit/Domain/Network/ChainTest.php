<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Event\ChainEnabled;
use App\Modules\Network\Domain\Event\ChainRegistered;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;

function makeChain(): Chain
{
    return Chain::register(
        id: new ChainId('ethereum-sepolia'),
        name: new ChainName('Ethereum Sepolia'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [new RpcEndpoint('https://rpc.sepolia.org', RpcKind::Http)],
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
}

it('registers as disabled by default and emits ChainRegistered', function (): void {
    $chain = makeChain();

    expect($chain->isEnabled())->toBeFalse();
    $events = $chain->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(ChainRegistered::class);

    // pull is destructive
    expect($chain->pullPendingEvents())->toBe([]);
});

it('emits both Registered and Enabled when enabled on creation', function (): void {
    $chain = makeChain();
    $chain->enable(new DateTimeImmutable('2026-01-02T00:00:00Z'));

    $events = $chain->pullPendingEvents();
    expect($events)->toHaveCount(2);
    expect($events[0])->toBeInstanceOf(ChainRegistered::class);
    expect($events[1])->toBeInstanceOf(ChainEnabled::class);
});

it('is idempotent on enable', function (): void {
    $chain = makeChain();
    $chain->enable(new DateTimeImmutable());
    $chain->pullPendingEvents();          // drain
    $chain->enable(new DateTimeImmutable());

    expect($chain->pullPendingEvents())->toBe([]);
});

it('requires at least one endpoint', function (): void {
    Chain::register(
        id: new ChainId('eth-sep'),
        name: new ChainName('X'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [],
        now: new DateTimeImmutable(),
    );
})->throws(\InvalidArgumentException::class);
