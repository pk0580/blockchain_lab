<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Network;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;

it('round-trips a Chain aggregate', function (): void {
    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);

    $chain = Chain::register(
        id: new ChainId('ethereum-sepolia'),
        name: new ChainName('Ethereum Sepolia'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [
            new RpcEndpoint('https://rpc.sepolia.org', RpcKind::Http, priority: 10),
            new RpcEndpoint('wss://ws.sepolia.example', RpcKind::WebSocket, priority: 50),
        ],
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
    $chain->enable(new DateTimeImmutable('2026-01-02T00:00:00Z'));

    $repo->save($chain);

    $loaded = $repo->findById(new ChainId('ethereum-sepolia'));
    expect($loaded)->not->toBeNull();
    expect($loaded->family)->toBe(ChainFamily::Evm);
    expect($loaded->isEnabled())->toBeTrue();
    expect($loaded->endpoints())->toHaveCount(2);
    expect($loaded->confirmationRequirement->requiredConfirmations)->toBe(12);
});

it('lists only enabled chains', function (): void {
    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);

    $a = Chain::register(
        id: new ChainId('chain-a'),
        name: new ChainName('A'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('AAA', 18),
        confirmationRequirement: new ConfirmationRequirement(1, 10),
        endpoints: [new RpcEndpoint('https://a.example', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
    $a->enable(new DateTimeImmutable());

    $b = Chain::register(
        id: new ChainId('chain-b'),
        name: new ChainName('B'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BBB', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 10),
        endpoints: [new RpcEndpoint('https://b.example', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );

    $repo->save($a);
    $repo->save($b);

    $enabled = $repo->allEnabled();
    expect($enabled)->toHaveCount(1);
    expect($enabled[0]->id->value)->toBe('chain-a');
});
