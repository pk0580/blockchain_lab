<?php

declare(strict_types=1);

namespace Tests\Feature\Withdrawal;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations\UpdateWithdrawalConfirmationsAction;
use App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations\UpdateWithdrawalConfirmationsData;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookupRegistry;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\ConfirmationObservation;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use App\Modules\Withdrawal\Infrastructure\Job\WatchWithdrawalConfirmationsJob;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class FakeConfirmationLookup implements WithdrawalConfirmationLookup
{
    public int $calls = 0;

    public function __construct(public ConfirmationObservation $observation) {}

    public function observe(Chain $chain, TxHash $txHash): ConfirmationObservation
    {
        $this->calls++;
        return $this->observation;
    }
}

final class FakeConfirmationRegistry implements WithdrawalConfirmationLookupRegistry
{
    public function __construct(private WithdrawalConfirmationLookup $lookup) {}

    public function for(ChainFamily $family): WithdrawalConfirmationLookup
    {
        return $this->lookup;
    }
}

function registerBitcoinChainForPoll(int $required = 3): Chain
{
    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $chain = Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('Bitcoin Regtest'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement($required, 6),
        endpoints: [new RpcEndpoint('http://bitcoin-regtest:18443', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
    $chain->enable(new DateTimeImmutable());
    $repo->save($chain);
    return $chain;
}

function makeBroadcastedRow(string $idSuffix, string $key, string $txChar = 'e'): Withdrawal
{
    $w = Withdrawal::request(
        id: new WithdrawalId('00000000-0000-4000-8000-'.str_pad($idSuffix, 12, '0', STR_PAD_LEFT)),
        walletId: new WalletId('w-poll'),
        chainId: new ChainId('bitcoin-regtest'),
        hotAddress: new HotAddress('bcrt1qhotwallet'),
        toAddress: new Address('bcrt1qrecipient00000000'),
        amount: new WithdrawalAmount('50000'),
        currency: new Currency('BTC'),
        feeQuote: new FeeQuoteSnapshot(
            priority: 'standard',
            breakdown: ['family' => 'bitcoin', 'sat_per_vbyte' => 5],
            estimatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ),
        idempotencyKey: new IdempotencyKey($key),
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
    $now = new DateTimeImmutable('2026-01-01T00:01:00Z');
    $w->markAsBuilt('deadbeef', null, ['inputs' => [['txid' => 'aa', 'vout' => 0]]], $now);
    $w->markAsSigned('cafebabe', $now);
    $w->markAsBroadcasted(new TxHash(str_repeat($txChar, 64)), $now);
    $w->pullPendingEvents();
    return $w;
}

it('moves Broadcasted → Confirming when observation < required', function (): void {
    registerBitcoinChainForPoll(required: 3);

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $row = makeBroadcastedRow('1', 'idem-poll-1');
    $repo->save($row);

    $this->app->instance(
        WithdrawalConfirmationLookupRegistry::class,
        new FakeConfirmationRegistry(new FakeConfirmationLookup(ConfirmationObservation::confirmed(1))),
    );

    /** @var UpdateWithdrawalConfirmationsAction $action */
    $action = app(UpdateWithdrawalConfirmationsAction::class);
    $result = $action->handle(new UpdateWithdrawalConfirmationsData($row->id->value));

    expect($result->status)->toBe(WithdrawalStatus::Confirming);
    expect($result->confirmations)->toBe(1);
    expect($result->changed)->toBeTrue();

    $reloaded = $repo->findById($row->id);
    expect($reloaded?->status())->toBe(WithdrawalStatus::Confirming);
});

it('moves Broadcasted → Confirmed when observation reaches required', function (): void {
    registerBitcoinChainForPoll(required: 3);

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $row = makeBroadcastedRow('2', 'idem-poll-2');
    $repo->save($row);

    $this->app->instance(
        WithdrawalConfirmationLookupRegistry::class,
        new FakeConfirmationRegistry(new FakeConfirmationLookup(ConfirmationObservation::confirmed(3))),
    );

    /** @var UpdateWithdrawalConfirmationsAction $action */
    $action = app(UpdateWithdrawalConfirmationsAction::class);
    $result = $action->handle(new UpdateWithdrawalConfirmationsData($row->id->value));

    expect($result->status)->toBe(WithdrawalStatus::Confirmed);
    expect($result->confirmations)->toBe(3);

    $reloaded = $repo->findById($row->id);
    expect($reloaded?->confirmedAt())->not->toBeNull();
});

it('is a no-op when observation is pending', function (): void {
    registerBitcoinChainForPoll(required: 3);

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $row = makeBroadcastedRow('3', 'idem-poll-3');
    $repo->save($row);

    $this->app->instance(
        WithdrawalConfirmationLookupRegistry::class,
        new FakeConfirmationRegistry(new FakeConfirmationLookup(ConfirmationObservation::pending())),
    );

    /** @var UpdateWithdrawalConfirmationsAction $action */
    $action = app(UpdateWithdrawalConfirmationsAction::class);
    $result = $action->handle(new UpdateWithdrawalConfirmationsData($row->id->value));

    expect($result->changed)->toBeFalse();
    expect($result->status)->toBe(WithdrawalStatus::Broadcasted);
});

it('runs the batch job over all active rows on a chain', function (): void {
    registerBitcoinChainForPoll(required: 3);

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $repo->save(makeBroadcastedRow('a', 'idem-batch-a', txChar: 'a'));
    $repo->save(makeBroadcastedRow('b', 'idem-batch-b', txChar: 'b'));

    $this->app->instance(
        WithdrawalConfirmationLookupRegistry::class,
        new FakeConfirmationRegistry(new FakeConfirmationLookup(ConfirmationObservation::confirmed(6))),
    );

    $job = new WatchWithdrawalConfirmationsJob('bitcoin-regtest', limit: 50);
    $job->handle($repo, app(UpdateWithdrawalConfirmationsAction::class));

    expect($repo->findActiveByChain(new ChainId('bitcoin-regtest'), 50))->toBe([]);
});
