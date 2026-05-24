<?php

declare(strict_types=1);

namespace Tests\Feature\Playground;

it('GET /api/playground/gas/chart returns 30 samples', function (): void {
    $this->getJson('/api/playground/gas/chart?seed=42')
        ->assertOk()
        ->assertJsonPath('data.unit', 'gwei / seconds')
        ->assertJsonCount(30, 'data.samples')
        ->assertJsonPath('data.samples.0.priority_fee_gwei', 1)
        ->assertJsonPath('data.samples.29.priority_fee_gwei', 30);
});

it('gas chart is deterministic for a fixed seed', function (): void {
    $a = $this->getJson('/api/playground/gas/chart?seed=123')->json('data.samples');
    $b = $this->getJson('/api/playground/gas/chart?seed=123')->json('data.samples');
    expect($a)->toBe($b);
});

it('POST /api/playground/nonce/simulate detects when B replaces A', function (): void {
    $this->postJson('/api/playground/nonce/simulate', [
        'nonce' => 7,
        'tx_a' => ['tag' => 'A', 'priority_fee_gwei' => 10],
        'tx_b' => ['tag' => 'B', 'priority_fee_gwei' => 11],
        'replacement_bump_percent' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('data.winner_tag', 'B')
        ->assertJsonPath('data.threshold_priority_fee_gwei', 11);
});

it('POST /api/playground/nonce/simulate detects when A keeps the slot', function (): void {
    $this->postJson('/api/playground/nonce/simulate', [
        'nonce' => 7,
        'tx_a' => ['tag' => 'A', 'priority_fee_gwei' => 10],
        'tx_b' => ['tag' => 'B', 'priority_fee_gwei' => 10.5],
        'replacement_bump_percent' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('data.winner_tag', 'A');
});

it('nonce simulate validates inputs', function (): void {
    $this->postJson('/api/playground/nonce/simulate', [
        'nonce' => -1,
        'tx_a' => ['tag' => 'A', 'priority_fee_gwei' => 1],
        'tx_b' => ['tag' => 'B', 'priority_fee_gwei' => 2],
    ])->assertUnprocessable();
});
