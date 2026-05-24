<?php

declare(strict_types=1);

namespace Tests\Feature\NodeHealth;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints\ProbeChainEndpointsAction;
use App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints\ProbeChainEndpointsData;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\Event\EndpointHealthChanged;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;
use App\Modules\NodeHealth\Infrastructure\Job\ProbeChainEndpointsJob;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

function registerEvmChainForProbe(): Chain
{
    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $chain = Chain::register(
        id: new ChainId('evm-probe'),
        name: new ChainName('EVM Probe'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [
            new RpcEndpoint('https://primary.example/', RpcKind::Http),
            new RpcEndpoint('https://backup.example/', RpcKind::Http),
        ],
        now: new DateTimeImmutable(),
    );
    $chain->enable(new DateTimeImmutable());
    $repo->save($chain);
    return $chain;
}

it('marks responding EVM endpoint Healthy and broken one Unhealthy', function (): void {
    $chain = registerEvmChainForProbe();

    Http::fake([
        'primary.example/*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1, 'result' => '0x12345',
        ], 200),
        'backup.example/*' => Http::response('upstream is down', 500),
    ]);

    /** @var ProbeChainEndpointsAction $action */
    $action = app(ProbeChainEndpointsAction::class);
    $result = $action->handle(new ProbeChainEndpointsData($chain->id->value));

    expect($result->probed)->toHaveCount(2);

    /** @var EndpointHealthRegistry $registry */
    $registry = app(EndpointHealthRegistry::class);
    expect($registry->status(new EndpointKey($chain->id, 'https://primary.example/')))
        ->toBe(EndpointStatus::Healthy);
    expect($registry->status(new EndpointKey($chain->id, 'https://backup.example/')))
        ->toBe(EndpointStatus::Unhealthy);
});

it('emits EndpointHealthChanged only on status transitions', function (): void {
    $chain = registerEvmChainForProbe();

    Http::fake([
        'primary.example/*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1, 'result' => '0x100',
        ], 200),
        'backup.example/*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1, 'result' => '0x100',
        ], 200),
    ]);

    Event::fake([EndpointHealthChanged::class]);

    /** @var ProbeChainEndpointsAction $action */
    $action = app(ProbeChainEndpointsAction::class);
    $action->handle(new ProbeChainEndpointsData($chain->id->value));
    $action->handle(new ProbeChainEndpointsData($chain->id->value));

    // First run: Unknown → Healthy x 2. Second run: Healthy → Healthy (no event).
    Event::assertDispatchedTimes(EndpointHealthChanged::class, 2);
});

it('ProbeChainEndpointsJob dispatches probes through the action', function (): void {
    $chain = registerEvmChainForProbe();

    Http::fake([
        '*.example/*' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1, 'result' => '0x200',
        ], 200),
    ]);

    (new ProbeChainEndpointsJob($chain->id->value))
        ->handle(app(ProbeChainEndpointsAction::class));

    /** @var EndpointHealthRegistry $registry */
    $registry = app(EndpointHealthRegistry::class);
    expect($registry->status(new EndpointKey($chain->id, 'https://primary.example/')))
        ->toBe(EndpointStatus::Healthy);
});
