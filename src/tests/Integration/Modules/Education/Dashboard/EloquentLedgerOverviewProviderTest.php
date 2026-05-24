<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Education\Dashboard;

use App\Modules\Education\Application\Contract\Dashboard\LedgerOverviewProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertLedger(array $overrides = []): void
{
    DB::table('ledger_entries')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'wallet_id' => (string) Str::uuid(),
        'chain_id' => 'bitcoin-regtest',
        'direction' => 'credit',
        'amount' => '100',
        'currency' => 'BTC',
        'operation_type' => 'deposit',
        'operation_ref' => 'op-'.Str::random(8),
        'related_tx_hash' => str_repeat('a', 64),
        'block_height' => 100,
        'reverses_entry_id' => null,
        'status' => 'confirmed',
        'created_at' => now(),
    ], $overrides));
}

it('returns zeros when no entries exist', function (): void {
    /** @var LedgerOverviewProvider $provider */
    $provider = app(LedgerOverviewProvider::class);
    $section = $provider->load();

    expect($section->totalEntries)->toBe(0);
    expect($section->countsByStatus)->toBe([]);
    expect($section->recentEntries)->toBe([]);
});

it('counts by status and returns most recent 20 entries', function (): void {
    for ($i = 0; $i < 22; $i++) {
        insertLedger([
            'created_at' => now()->subSeconds(22 - $i),
            'status' => $i % 3 === 0 ? 'pending' : 'confirmed',
        ]);
    }
    insertLedger(['status' => 'reversed', 'created_at' => now()]);

    /** @var LedgerOverviewProvider $provider */
    $provider = app(LedgerOverviewProvider::class);
    $section = $provider->load();

    expect($section->totalEntries)->toBe(23);
    expect($section->countsByStatus)->toMatchArray([
        'reversed' => 1,
    ]);
    expect(array_sum($section->countsByStatus))->toBe(23);
    expect($section->recentEntries)->toHaveCount(20);
});
