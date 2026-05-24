<?php

declare(strict_types=1);

namespace Tests\Feature\Education;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    Cache::flush();

    // Регтест-узел для mempool-провайдера: URL заранее, фейк ставит каждый тест сам
    // (Http::fake() в beforeEach + override в test не работает — Laravel
    // аккумулирует stubs и первый по порядку wins).
    config([
        'education.regtest.url' => 'http://bitcoin-regtest.test:18443',
        'education.regtest.user' => 'bitcoin',
        'education.regtest.password' => 'bitcoin',
    ]);

    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $chain = Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('Bitcoin regtest'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint('http://bitcoin-regtest.test:18443', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
    $chain->enable(new DateTimeImmutable());
    $repo->save($chain);
});

function fakeRegtestMempool(array $txids): void
{
    Http::fake([
        'http://bitcoin-regtest.test:18443*' => Http::response([
            'result' => $txids,
            'error' => null,
            'id' => 'getrawmempool',
        ], 200),
    ]);
}

it('renders the admin dashboard page with all 5 sections', function (): void {
    fakeRegtestMempool(['txid-a', 'txid-b']);

    $this->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('dashboard', fn (Assert $d) => $d
                ->has('node_health.rows')
                ->has('mempool.rows')
                ->has('ledger')
                ->has('withdrawals')
                ->has('outbox')
                ->has('generated_at')
            )
        );
});

it('mempool section includes Bitcoin tx count from regtest fake', function (): void {
    fakeRegtestMempool(['txid-a', 'txid-b']);

    $this->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.mempool.rows.0.chain_family', 'bitcoin')
            ->where('dashboard.mempool.rows.0.tx_count', 2)
            ->etc()
        );
});

it('exposes JSON polling endpoint', function (): void {
    fakeRegtestMempool(['txid-a', 'txid-b']);

    $this->getJson('/api/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.mempool.rows.0.tx_count', 2)
        ->assertJsonPath('data.node_health.rows.0.chain_id', 'bitcoin-regtest')
        ->assertJsonStructure([
            'data' => [
                'node_health' => ['rows'],
                'mempool' => ['rows'],
                'ledger' => ['total_entries', 'counts_by_status', 'recent_entries'],
                'withdrawals' => ['counts_by_status', 'recent'],
                'outbox' => [
                    'unpublished_messages',
                    'oldest_unpublished_at',
                    'deliveries_pending',
                    'deliveries_failed',
                    'deliveries_delivered',
                ],
                'generated_at',
            ],
        ]);
});

it('reports mempool error string when regtest RPC fails', function (): void {
    Http::fake([
        'http://bitcoin-regtest.test:18443*' => Http::response([
            'result' => null,
            'error' => ['code' => -28, 'message' => 'rpc warming up'],
            'id' => 'getrawmempool',
        ], 200),
    ]);

    $this->getJson('/api/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.mempool.rows.0.tx_count', null)
        ->assertJsonPath('data.mempool.rows.0.error', fn ($v) => is_string($v) && str_contains($v, 'rpc warming up'));
});
