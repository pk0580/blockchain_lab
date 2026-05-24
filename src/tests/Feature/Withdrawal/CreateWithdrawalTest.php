<?php

declare(strict_types=1);

namespace Tests\Feature\Withdrawal;

use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Contract\FeeEstimatorRegistry;
use App\Modules\Fee\Domain\Exception\UnsupportedChainFamilyException;
use App\Modules\Fee\Domain\ValueObject\BitcoinFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\ChainAdapterRegistry;
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
use App\Modules\Network\Domain\Contract\AddressValidator;
use App\Modules\Network\Domain\Contract\FinalityPolicy;
use App\Modules\Network\Domain\Contract\FeeEstimator as NetworkFeeEstimator;
use App\Modules\Network\Infrastructure\Registry\ConfirmationBasedFinality;
use App\Modules\Withdrawal\Application\Contract\HotWalletResolver;
use App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\HotWalletDescriptor;
use App\Modules\Withdrawal\Domain\Contract\TxBuilder;
use App\Modules\Withdrawal\Domain\Contract\TxBuilderRegistry;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\BuiltTransaction;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models\WithdrawalModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // array-cache переживает RefreshDatabase; сбрасываем «глобальное» chain-pause
    // состояние, чтобы соседние тесты не подсовывали 503.
    Cache::flush();
});

final class FakeBitcoinTxBuilder implements TxBuilder
{
    public int $calls = 0;

    public function build(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $nonce,
    ): BuiltTransaction {
        $this->calls++;
        return new BuiltTransaction(rawHex: 'deadbeef', signingExtras: ['inputs' => []]);
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
        return $this->build($chain, $from, $to, $amount, $fee, $previousNonce);
    }
}

final class FakeTxBuilderRegistry implements TxBuilderRegistry
{
    /** @var array<string, TxBuilder> */
    public array $byFamily;

    /**
     * @param array<string, TxBuilder> $byFamily
     */
    public function __construct(array $byFamily)
    {
        $this->byFamily = $byFamily;
    }

    public function for(ChainFamily $family): TxBuilder
    {
        return $this->byFamily[$family->value]
            ?? throw new \RuntimeException("no builder for {$family->value}");
    }
}

final class FakeBroadcastingChainAdapter implements ChainAdapter, AddressValidator, NetworkFeeEstimator
{
    public ?SignedRawTx $broadcasted = null;

    public function __construct(private Chain $chain, private TxHash $txHash) {}

    public function chain(): Chain
    {
        return $this->chain;
    }

    public function family(): ChainFamily
    {
        return $this->chain->family;
    }

    public function finality(): FinalityPolicy
    {
        return new ConfirmationBasedFinality($this->chain->confirmationRequirement);
    }

    public function addressValidator(): AddressValidator
    {
        return $this;
    }

    public function feeEstimator(): NetworkFeeEstimator
    {
        return $this;
    }

    public function currentHead(): BlockHeight
    {
        return new BlockHeight(0);
    }

    public function isHealthy(): bool
    {
        return true;
    }

    public function supports(TxHash $txHash): bool
    {
        return true;
    }

    public function isValid(Address $address): bool
    {
        return true;
    }

    public function broadcast(SignedRawTx $tx): TxHash
    {
        $this->broadcasted = $tx;
        return $this->txHash;
    }
}

final class FakeChainAdapterRegistry implements ChainAdapterRegistry
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

final class FakeSigningClientForWithdrawal implements SigningClient
{
    public ?string $lastRawHex = null;

    public function ensureSeed(string $reference, ?string $importMnemonic = null): bool
    {
        return true;
    }

    public function deriveAddress(string $seedReference, ChainFamily $family, string $path): Address
    {
        return new Address('bcrt1qderived000000');
    }

    public function isAddressValid(ChainFamily $family, string $address): bool
    {
        return true;
    }

    public function signRawTx(
        ChainFamily $family,
        string $seedReference,
        string $path,
        string $rawHex,
        array $extra = [],
    ): SignedRawTx {
        $this->lastRawHex = $rawHex;
        return new SignedRawTx($family, $rawHex.'ee');
    }
}

final class FakeHotWalletResolver implements HotWalletResolver
{
    public function resolve(Chain $chain): HotWalletDescriptor
    {
        return new HotWalletDescriptor(
            address: new HotAddress('bcrt1qhotwallet'),
            seedReference: 'seed-btc',
            derivationPath: "m/44'/0'/0'/1/0",
        );
    }
}

final class FakeBitcoinEstimator implements FeeEstimator
{
    public function estimate(Chain $chain, FeePriority $priority): FeeQuote
    {
        return new FeeQuote(
            chainId: $chain->id,
            priority: $priority,
            breakdown: new BitcoinFeeBreakdown(5),
            estimatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }
}

final class FakeFeeRegistry implements FeeEstimatorRegistry
{
    public function for(ChainFamily $family): FeeEstimator
    {
        if ($family !== ChainFamily::Bitcoin) {
            throw UnsupportedChainFamilyException::forFamily($family);
        }
        return new FakeBitcoinEstimator();
    }
}

function registerBitcoinChainForCreate(): Chain
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

beforeEach(function (): void {
    $chain = registerBitcoinChainForCreate();
    $adapter = new FakeBroadcastingChainAdapter($chain, new TxHash(str_repeat('a', 64)));

    $this->app->instance(ChainAdapterRegistry::class, new FakeChainAdapterRegistry($adapter));
    $this->app->instance(TxBuilderRegistry::class, new FakeTxBuilderRegistry([
        ChainFamily::Bitcoin->value => new FakeBitcoinTxBuilder(),
    ]));
    $this->app->instance(SigningClient::class, new FakeSigningClientForWithdrawal());
    $this->app->instance(HotWalletResolver::class, new FakeHotWalletResolver());
    $this->app->instance(FeeEstimatorRegistry::class, new FakeFeeRegistry());
});

it('creates a withdrawal end-to-end and reaches Broadcasted (202)', function (): void {
    $payload = [
        'wallet_id' => 'wallet-1',
        'chain_id' => 'bitcoin-regtest',
        'to_address' => 'bcrt1qrecipient00000000',
        'amount' => '50000',
        'currency' => 'BTC',
        'priority' => 'standard',
    ];

    $response = $this->withHeaders(['Idempotency-Key' => 'idem-happy-path-001'])
        ->postJson('/api/v1/withdrawals', $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', WithdrawalStatus::Broadcasted->value)
        ->assertJsonPath('data.tx_hash', str_repeat('a', 64));

    expect(WithdrawalModel::query()->count())->toBe(1);
    /** @var WithdrawalModel $row */
    $row = WithdrawalModel::query()->first();
    expect($row->status)->toBe(WithdrawalStatus::Broadcasted->value);
    expect($row->idempotency_key)->toBe('idem-happy-path-001');
});

it('replays the cached response on a repeat request (middleware-level idempotency)', function (): void {
    $payload = [
        'wallet_id' => 'wallet-2',
        'chain_id' => 'bitcoin-regtest',
        'to_address' => 'bcrt1qrecipient00000001',
        'amount' => '60000',
        'currency' => 'BTC',
        'priority' => 'standard',
    ];

    $first = $this->withHeaders(['Idempotency-Key' => 'idem-replay-001'])
        ->postJson('/api/v1/withdrawals', $payload);
    $second = $this->withHeaders(['Idempotency-Key' => 'idem-replay-001'])
        ->postJson('/api/v1/withdrawals', $payload);

    // Phase 7.3 — middleware абсорбирует replay'и ДО контроллера, поэтому второй
    // ответ — это ровно первый (202 + тот же body) + хедер X-Idempotent-Replay.
    $first->assertStatus(202);
    $second->assertStatus(202);
    expect($second->headers->get('X-Idempotent-Replay'))->toBe('true');
    expect($second->getContent())->toBe($first->getContent());
    expect(WithdrawalModel::query()->count())->toBe(1);
});

it('returns 409 when the Idempotency-Key is reused with a different payload', function (): void {
    $base = [
        'wallet_id' => 'wallet-3',
        'chain_id' => 'bitcoin-regtest',
        'to_address' => 'bcrt1qrecipient00000002',
        'amount' => '70000',
        'currency' => 'BTC',
        'priority' => 'standard',
    ];

    $this->withHeaders(['Idempotency-Key' => 'idem-conflict-001'])
        ->postJson('/api/v1/withdrawals', $base)
        ->assertStatus(202);

    $conflict = $this->withHeaders(['Idempotency-Key' => 'idem-conflict-001'])
        ->postJson('/api/v1/withdrawals', ['...' => '...'] + ['amount' => '99999'] + $base);
    $conflict->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
});

it('returns 422 when Idempotency-Key header is missing', function (): void {
    $payload = [
        'wallet_id' => 'wallet-4',
        'chain_id' => 'bitcoin-regtest',
        'to_address' => 'bcrt1qrecipient00000003',
        'amount' => '80000',
        'currency' => 'BTC',
        'priority' => 'standard',
    ];

    $this->postJson('/api/v1/withdrawals', $payload)
        ->assertStatus(422);
});

it('returns 422 on missing amount', function (): void {
    $this->withHeaders(['Idempotency-Key' => 'idem-validation-001'])
        ->postJson('/api/v1/withdrawals', [
            'wallet_id' => 'w', 'chain_id' => 'bitcoin-regtest',
            'to_address' => 'bcrt1qrecipient00000004', 'currency' => 'BTC',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('binds the Withdrawal repository in the container', function (): void {
    expect(app(WithdrawalRepository::class))->not->toBeNull();
});
