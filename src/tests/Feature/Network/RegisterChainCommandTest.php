<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a chain through the artisan command', function (): void {
    $this->artisan('chain:register', [
        'id' => 'ethereum-sepolia',
        '--name' => 'Ethereum Sepolia',
        '--family' => 'evm',
        '--currency' => 'ETH',
        '--decimals' => 18,
        '--confirmations' => 12,
        '--max-reorg' => 64,
        '--rpc' => ['https://rpc.sepolia.org'],
    ])->assertExitCode(0);

    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $chain = $repo->findById(new ChainId('ethereum-sepolia'));

    expect($chain)->not->toBeNull();
    expect($chain->isEnabled())->toBeTrue();
});

it('refuses to register the same chain twice', function (): void {
    $this->artisan('chain:register', [
        'id' => 'bitcoin-regtest',
        '--family' => 'bitcoin',
        '--currency' => 'BTC',
        '--decimals' => 8,
        '--confirmations' => 1,
        '--max-reorg' => 10,
        '--rpc' => ['http://bitcoin-regtest:18443'],
    ])->assertExitCode(0);

    $this->artisan('chain:register', [
        'id' => 'bitcoin-regtest',
        '--family' => 'bitcoin',
        '--currency' => 'BTC',
        '--decimals' => 8,
        '--confirmations' => 1,
        '--max-reorg' => 10,
        '--rpc' => ['http://bitcoin-regtest:18443'],
    ])->assertFailed();
});
