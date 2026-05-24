<?php

declare(strict_types=1);

namespace Tests\Feature\NodeHealth;

use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\NoRpcEndpointException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Network\Infrastructure\Picker\FirstHttpEndpointPicker;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use App\Modules\NodeHealth\Infrastructure\Picker\HealthBasedRpcEndpointPicker;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

function makeChainWithEndpoints(string $id, array $urls): Chain
{
    $endpoints = [];
    foreach ($urls as $url) {
        $endpoints[] = new RpcEndpoint($url, RpcKind::Http);
    }
    $chain = Chain::register(
        id: new ChainId($id),
        name: new ChainName('Test '.$id),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: $endpoints,
        now: new DateTimeImmutable(),
    );
    $chain->enable(new DateTimeImmutable());

    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $repo->save($chain);
    return $chain;
}

it('FirstHttpEndpointPicker returns first http endpoint', function (): void {
    $chain = makeChainWithEndpoints('chain-picker-1', ['http://a/', 'http://b/']);
    $picker = new FirstHttpEndpointPicker();
    expect($picker->pick($chain)->url)->toBe('http://a/');
});

it('FirstHttpEndpointPicker throws when no matching kind', function (): void {
    $chain = Chain::register(
        id: new ChainId('chain-picker-ws'),
        name: new ChainName('ws-only'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint('wss://only-ws/', RpcKind::WebSocket)],
        now: new DateTimeImmutable(),
    );
    expect(fn () => (new FirstHttpEndpointPicker())->pick($chain))
        ->toThrow(NoRpcEndpointException::class);
});

it('HealthBasedRpcEndpointPicker prefers Healthy over Degraded over Unknown', function (): void {
    $chain = makeChainWithEndpoints('chain-picker-2', [
        'http://degraded.example/', 'http://healthy.example/', 'http://unknown.example/',
    ]);

    /** @var EndpointHealthRegistry $registry */
    $registry = app(EndpointHealthRegistry::class);
    $now = new DateTimeImmutable();
    $registry->record(
        new EndpointKey($chain->id, 'http://degraded.example/'),
        EndpointObservation::degraded(100, 2500, $now, 'slow'),
    );
    $registry->record(
        new EndpointKey($chain->id, 'http://healthy.example/'),
        EndpointObservation::healthy(100, 50, $now),
    );

    $picker = new HealthBasedRpcEndpointPicker($registry);
    expect($picker->pick($chain)->url)->toBe('http://healthy.example/');
});

it('HealthBasedRpcEndpointPicker skips Unhealthy endpoints', function (): void {
    $chain = makeChainWithEndpoints('chain-picker-3', [
        'http://unhealthy.example/', 'http://healthy.example/',
    ]);

    /** @var EndpointHealthRegistry $registry */
    $registry = app(EndpointHealthRegistry::class);
    $now = new DateTimeImmutable();
    $registry->record(
        new EndpointKey($chain->id, 'http://unhealthy.example/'),
        EndpointObservation::unhealthy('connection refused', $now),
    );

    $picker = new HealthBasedRpcEndpointPicker($registry);
    expect($picker->pick($chain)->url)->toBe('http://healthy.example/');
});

it('HealthBasedRpcEndpointPicker throws when every endpoint is Unhealthy', function (): void {
    $chain = makeChainWithEndpoints('chain-picker-4', [
        'http://dead-a.example/', 'http://dead-b.example/',
    ]);

    /** @var EndpointHealthRegistry $registry */
    $registry = app(EndpointHealthRegistry::class);
    $now = new DateTimeImmutable();
    foreach (['http://dead-a.example/', 'http://dead-b.example/'] as $url) {
        $registry->record(new EndpointKey($chain->id, $url), EndpointObservation::unhealthy('no', $now));
    }

    expect(fn () => (new HealthBasedRpcEndpointPicker($registry))->pick($chain))
        ->toThrow(NoRpcEndpointException::class);
});

it('container resolves RpcEndpointPicker to HealthBased after NodeHealthServiceProvider', function (): void {
    expect(app(RpcEndpointPicker::class))->toBeInstanceOf(HealthBasedRpcEndpointPicker::class);
});
