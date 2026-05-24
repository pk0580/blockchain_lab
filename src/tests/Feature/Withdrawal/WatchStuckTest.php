<?php

declare(strict_types=1);

namespace Tests\Feature\Withdrawal;

use App\Modules\Network\Domain\Contract\AddressValidator;
use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\ChainAdapterRegistry;
use App\Modules\Network\Domain\Contract\FeeEstimator as NetworkFeeEstimator;
use App\Modules\Network\Domain\Contract\FinalityPolicy;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Network\Infrastructure\Registry\ConfirmationBasedFinality;
use App\Modules\Withdrawal\Application\Contract\HotWalletResolver;
use App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals\MarkStuckWithdrawalsAction;
use App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals\MarkStuckWithdrawalsData;
use App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\HotWalletDescriptor;
use App\Modules\Withdrawal\Domain\Contract\TxBuilder;
use App\Modules\Withdrawal\Domain\Contract\TxBuilderRegistry;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\BuiltTransaction;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class RbfRecordingBitcoinTxBuilder implements TxBuilder
{
    public ?BuiltTransaction $lastRebuild = null;

    public function build(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $nonce,
    ): BuiltTransaction {
        return new BuiltTransaction(rawHex: 'orig', signingExtras: []);
    }

    public function rebuild(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $previousNonce,
        ?array $previousExtras,
    ): BuiltTransaction {
        // hex-safe payload (только 0-9 a-f), длина чётная — иначе SignedRawTx
        // отвергнет результат signing-сервиса при broadcast.
        $rawHex = str_pad(dechex((int) ($fee->breakdown['sat_per_vbyte'] ?? 0)), 8, '0', STR_PAD_LEFT);
        $this->lastRebuild = new BuiltTransaction(
            rawHex: $rawHex,
            signingExtras: array_merge($previousExtras ?? [], ['replacement' => true, 'fee' => $fee->breakdown]),
        );
        return $this->lastRebuild;
    }
}

final class RbfTxBuilderRegistry implements TxBuilderRegistry
{
    public function __construct(public RbfRecordingBitcoinTxBuilder $builder) {}

    public function for(ChainFamily $family): TxBuilder
    {
        return $this->builder;
    }
}

final class RbfChainAdapter implements ChainAdapter, AddressValidator, NetworkFeeEstimator
{
    public ?SignedRawTx $broadcasted = null;

    public function __construct(private Chain $chain, private TxHash $tx) {}

    public function chain(): Chain { return $this->chain; }
    public function family(): ChainFamily { return $this->chain->family; }
    public function finality(): FinalityPolicy { return new ConfirmationBasedFinality($this->chain->confirmationRequirement); }
    public function addressValidator(): AddressValidator { return $this; }
    public function feeEstimator(): NetworkFeeEstimator { return $this; }
    public function currentHead(): BlockHeight { return new BlockHeight(0); }
    public function isHealthy(): bool { return true; }
    public function supports(TxHash $txHash): bool { return true; }
    public function isValid(Address $address): bool { return true; }
    public function broadcast(SignedRawTx $tx): TxHash
    {
        $this->broadcasted = $tx;
        return $this->tx;
    }
}

final class RbfChainAdapterRegistry implements ChainAdapterRegistry
{
    public function __construct(private ChainAdapter $adapter) {}

    public function adapterFor(ChainId $chainId): ChainAdapter
    {
        if (! $chainId->equals($this->adapter->chain()->id)) {
            throw ChainNotFoundException::byId($chainId);
        }
        return $this->adapter;
    }

    public function enabledAdapters(): array
    {
        return [$this->adapter];
    }
}

final class RbfHotWalletResolver implements HotWalletResolver
{
    public function resolve(Chain $chain): HotWalletDescriptor
    {
        return new HotWalletDescriptor(
            address: new HotAddress('bcrt1qhotwallet'),
            seedReference: 'seed-rbf',
            derivationPath: "m/44'/0'/0'/1/0",
        );
    }
}

final class RbfSigningClient implements SigningClient
{
    public ?string $lastRawHex = null;
    public function ensureSeed(string $reference, ?string $importMnemonic = null): bool { return true; }
    public function deriveAddress(string $seedReference, ChainFamily $family, string $path): Address { return new Address('bcrt1qderived'); }
    public function isAddressValid(ChainFamily $family, string $address): bool { return true; }
    public function signRawTx(
        ChainFamily $family,
        string $seedReference,
        string $path,
        string $rawHex,
        array $extra = [],
    ): SignedRawTx {
        $this->lastRawHex = $rawHex;
        // Безопасный фиктивный hex: используем оригинальный rawHex (предположительно hex)
        // + чётный suffix без неpresentation символов.
        return new SignedRawTx($family, $rawHex.'ff');
    }
}

function registerBitcoinChainForRbf(): Chain
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

function persistBroadcastedRow(string $idSuffix, string $idemKey, DateTimeImmutable $broadcastAt, string $txChar = 'a'): Withdrawal
{
    $w = Withdrawal::request(
        id: new WithdrawalId('00000000-0000-4000-8000-'.str_pad($idSuffix, 12, '0', STR_PAD_LEFT)),
        walletId: new WalletId('w-rbf'),
        chainId: new ChainId('bitcoin-regtest'),
        hotAddress: new HotAddress('bcrt1qhotwallet'),
        toAddress: new Address('bcrt1qrecipient'),
        amount: new WithdrawalAmount('50000'),
        currency: new Currency('BTC'),
        feeQuote: new FeeQuoteSnapshot(
            priority: 'standard',
            breakdown: ['family' => 'bitcoin', 'sat_per_vbyte' => 5],
            estimatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ),
        idempotencyKey: new IdempotencyKey($idemKey),
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
    $w->markAsBuilt('orig-hex', null, ['inputs' => [['txid' => 'aa', 'vout' => 0, 'amount_sat' => 100000]]], $broadcastAt);
    $w->markAsSigned('orig-signed', $broadcastAt);
    $w->markAsBroadcasted(new TxHash(str_repeat($txChar, 64)), $broadcastAt);
    $w->pullPendingEvents();

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $repo->save($w);
    return $w;
}

it('marks stale Broadcasted rows as Stuck and emits WithdrawalStuck', function (): void {
    registerBitcoinChainForRbf();

    $stale = persistBroadcastedRow(
        '1',
        'idem-rbf-1',
        (new DateTimeImmutable())->modify('-2 hours'),
        txChar: '1',
    );
    $fresh = persistBroadcastedRow(
        '2',
        'idem-rbf-2',
        (new DateTimeImmutable())->modify('-1 minute'),
        txChar: '2',
    );

    /** @var MarkStuckWithdrawalsAction $action */
    $action = app(MarkStuckWithdrawalsAction::class);
    $result = $action->handle(new MarkStuckWithdrawalsData(
        chainId: 'bitcoin-regtest',
        stuckAfterSeconds: 900,
    ));

    expect($result->count())->toBe(1);
    expect($result->markedIds)->toBe([$stale->id->value]);

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    expect($repo->findById($stale->id)?->status())->toBe(WithdrawalStatus::Stuck);
    expect($repo->findById($fresh->id)?->status())->toBe(WithdrawalStatus::Broadcasted);
});

it('rebuilds BTC withdrawal with bumped fee + replaces original via listener', function (): void {
    $chain = registerBitcoinChainForRbf();

    $builder = new RbfRecordingBitcoinTxBuilder();
    $this->app->instance(TxBuilderRegistry::class, new RbfTxBuilderRegistry($builder));
    $this->app->instance(
        ChainAdapterRegistry::class,
        new RbfChainAdapterRegistry(new RbfChainAdapter($chain, new TxHash(str_repeat('b', 64)))),
    );
    $this->app->instance(SigningClient::class, new RbfSigningClient());
    $this->app->instance(HotWalletResolver::class, new RbfHotWalletResolver());

    $stuck = persistBroadcastedRow(
        '3',
        'idem-rbf-3',
        (new DateTimeImmutable())->modify('-30 minutes'),
        txChar: '3',
    );

    /** @var MarkStuckWithdrawalsAction $stuckAction */
    $stuckAction = app(MarkStuckWithdrawalsAction::class);
    $stuckAction->handle(new MarkStuckWithdrawalsData('bitcoin-regtest', 900));

    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $original = $repo->findById($stuck->id);
    expect($original?->status())->toBe(WithdrawalStatus::Replaced);

    expect($builder->lastRebuild)->not->toBeNull();
    expect($builder->lastRebuild?->signingExtras['replacement'] ?? false)->toBeTrue();
    // Fee bumped: 5 sat/vB × 1.25 = 6.25 → max(6, 5+1) = 6, но bcdiv даёт floor — итог 6.
    expect((int) ($builder->lastRebuild?->signingExtras['fee']['sat_per_vbyte'] ?? 0))->toBe(6);

    // Replacement существует, новая row помечена idempotency_key = rbf:{original}.
    $replacement = $repo->findByIdempotencyKey(new IdempotencyKey('rbf:'.$original->id->value));
    expect($replacement)->not->toBeNull();
    expect($replacement?->status())->toBe(WithdrawalStatus::Broadcasted);
    expect($replacement?->replacementOf()?->value)->toBe($original->id->value);
});
