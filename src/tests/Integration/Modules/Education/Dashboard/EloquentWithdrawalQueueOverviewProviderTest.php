<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Education\Dashboard;

use App\Modules\Education\Application\Contract\Dashboard\WithdrawalQueueOverviewProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertWithdrawal(array $overrides = []): void
{
    DB::table('withdrawals')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'wallet_id' => (string) Str::uuid(),
        'chain_id' => 'bitcoin-regtest',
        'hot_address' => 'bc1qfake',
        'to_address' => 'bc1qrcvr',
        'amount' => '5000',
        'currency' => 'BTC',
        'fee_priority' => 'normal',
        'fee_breakdown_json' => json_encode([]),
        'nonce' => null,
        'tx_hash' => null,
        'confirmations' => 0,
        'raw_tx_hex' => null,
        'signing_extras' => null,
        'status' => 'requested',
        'failure_reason' => null,
        'replacement_of' => null,
        'idempotency_key' => 'idem-'.Str::random(10),
        'version' => 1,
        'requested_at' => now(),
        'broadcast_at' => null,
        'confirmed_at' => null,
    ], $overrides));
}

it('returns empty when no withdrawals', function (): void {
    /** @var WithdrawalQueueOverviewProvider $provider */
    $provider = app(WithdrawalQueueOverviewProvider::class);
    $section = $provider->load();

    expect($section->countsByStatus)->toBe([]);
    expect($section->recent)->toBe([]);
});

it('groups by status and returns up to 20 most recent rows', function (): void {
    foreach (['requested', 'broadcasted', 'confirmed', 'failed'] as $status) {
        insertWithdrawal(['status' => $status]);
    }
    for ($i = 0; $i < 18; $i++) {
        insertWithdrawal([
            'status' => 'confirming',
            'requested_at' => now()->subMinutes(18 - $i),
        ]);
    }

    /** @var WithdrawalQueueOverviewProvider $provider */
    $provider = app(WithdrawalQueueOverviewProvider::class);
    $section = $provider->load();

    expect($section->countsByStatus)->toMatchArray([
        'requested' => 1,
        'broadcasted' => 1,
        'confirmed' => 1,
        'failed' => 1,
        'confirming' => 18,
    ]);
    expect($section->recent)->toHaveCount(20);
});
