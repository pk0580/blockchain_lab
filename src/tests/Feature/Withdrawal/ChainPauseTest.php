<?php

declare(strict_types=1);

namespace Tests\Feature\Withdrawal;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\ReorgDetection\Domain\Event\ReorgTooDeep;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // ChainPauseRegistry хранит флаг в array-cache store, который переживает
    // RefreshDatabase. Без сброса пауза «протекает» в соседние тесты.
    Cache::flush();
});

function registerBitcoinChainForPause(): Chain
{
    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $chain = Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('Bitcoin Regtest'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint('http://bitcoin-regtest:18443', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
    $chain->enable(new DateTimeImmutable());
    $repo->save($chain);
    return $chain;
}

it('pauses the chain when ReorgTooDeep is dispatched', function (): void {
    registerBitcoinChainForPause();

    /** @var Dispatcher $events */
    $events = app(Dispatcher::class);
    /** @var ChainPauseRegistry $pauses */
    $pauses = app(ChainPauseRegistry::class);

    expect($pauses->isPaused(new ChainId('bitcoin-regtest')))->toBeFalse();

    $events->dispatch(new ReorgTooDeep(
        chainId: new ChainId('bitcoin-regtest'),
        orphanedHeight: new BlockHeight(100),
        observedDepth: 7,
        threshold: 6,
        occurredAt: new DateTimeImmutable(),
    ));

    expect($pauses->isPaused(new ChainId('bitcoin-regtest')))->toBeTrue();
});

it('returns 503 when posting to /api/v1/withdrawals on a paused chain', function (): void {
    registerBitcoinChainForPause();

    /** @var ChainPauseRegistry $pauses */
    $pauses = app(ChainPauseRegistry::class);
    $pauses->pause(new ChainId('bitcoin-regtest'), 3600);

    $payload = [
        'wallet_id' => 'wallet-paused',
        'chain_id' => 'bitcoin-regtest',
        'to_address' => 'bcrt1qrecipientPaused',
        'amount' => '10000',
        'currency' => 'BTC',
        'priority' => 'standard',
    ];

    $this->withHeaders(['Idempotency-Key' => 'idem-paused-001'])
        ->postJson('/api/v1/withdrawals', $payload)
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'chain_paused');
});
