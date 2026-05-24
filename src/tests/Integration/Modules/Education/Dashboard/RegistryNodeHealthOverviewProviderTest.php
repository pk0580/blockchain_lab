<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Education\Dashboard;

use App\Modules\Education\Application\Contract\Dashboard\NodeHealthOverviewProvider;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use DateTimeImmutable;

// Cache::flush() здесь не нужен: CACHE_STORE=array (per-process), RefreshDatabase
// сбрасывает БД, а тест пишет ровно те ключи которые сам читает. Не делаем flush
// чтобы не создавать race с параллельными NodeHealth-тестами.

function registerChain(string $id, array $urls, ChainFamily $family = ChainFamily::Evm): Chain
{
    $endpoints = [];
    foreach ($urls as $url => $kind) {
        $endpoints[] = new RpcEndpoint($url, $kind);
    }
    $chain = Chain::register(
        id: new ChainId($id),
        name: new ChainName('Test '.$id),
        family: $family,
        nativeCurrency: new NativeCurrency('TST', 18),
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

it('returns Unknown status when no probe ran yet', function (): void {
    registerChain('chain-dash-1', ['http://no-probe.example/' => RpcKind::Http]);

    /** @var NodeHealthOverviewProvider $provider */
    $provider = app(NodeHealthOverviewProvider::class);
    $section = $provider->load();

    expect($section->rows)->toHaveCount(1);
    $row = $section->rows[0];
    expect($row->status)->toBe('unknown');
    expect($row->headHeight)->toBeNull();
    expect($row->latencyMs)->toBeNull();
    expect($row->observedAt)->toBeNull();
    expect($row->error)->toBeNull();
});

it('projects last observation from registry', function (): void {
    $chain = registerChain('chain-dash-2', [
        'http://hot.example/' => RpcKind::Http,
        'http://cold.example/' => RpcKind::Http,
    ]);

    /** @var EndpointHealthRegistry $registry */
    $registry = app(EndpointHealthRegistry::class);
    $now = new DateTimeImmutable('2026-05-23T12:00:00Z');
    $registry->record(
        new EndpointKey($chain->id, 'http://hot.example/'),
        EndpointObservation::healthy(1234, 42, $now),
    );

    /** @var NodeHealthOverviewProvider $provider */
    $provider = app(NodeHealthOverviewProvider::class);
    $section = $provider->load();

    expect($section->rows)->toHaveCount(2);

    $hot = collect($section->rows)->firstWhere('endpointUrl', 'http://hot.example/');
    expect($hot->status)->toBe('healthy');
    expect($hot->headHeight)->toBe(1234);
    expect($hot->latencyMs)->toBe(42);
    expect($hot->observedAt)->toBe($now->format(DATE_ATOM));

    $cold = collect($section->rows)->firstWhere('endpointUrl', 'http://cold.example/');
    expect($cold->status)->toBe('unknown');
});

it('skips websocket endpoints', function (): void {
    registerChain('chain-dash-3', [
        'http://only-http.example/' => RpcKind::Http,
        'wss://ignored.example/' => RpcKind::WebSocket,
    ]);

    /** @var NodeHealthOverviewProvider $provider */
    $provider = app(NodeHealthOverviewProvider::class);
    $section = $provider->load();

    expect($section->rows)->toHaveCount(1);
    expect($section->rows[0]->endpointUrl)->toBe('http://only-http.example/');
});
