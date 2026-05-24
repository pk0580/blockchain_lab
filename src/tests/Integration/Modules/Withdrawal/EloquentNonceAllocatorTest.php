<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Withdrawal;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Withdrawal\Domain\Contract\NonceAllocator;
use App\Modules\Withdrawal\Domain\Contract\NonceProbe;
use App\Modules\Withdrawal\Domain\Contract\NonceProbeRegistry;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Infrastructure\Nonce\EloquentNonceAllocator;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models\NonceAssignmentModel;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;

final class FakeProbeRegistry implements NonceProbeRegistry
{
    /** @param array<string, NonceProbe> $byFamily */
    public function __construct(private array $byFamily) {}

    public function for(ChainFamily $family): ?NonceProbe
    {
        return $this->byFamily[$family->value] ?? null;
    }
}

final class FixedProbe implements NonceProbe
{
    public int $calls = 0;

    public function __construct(private readonly ?int $probedValue) {}

    public function probe(Chain $chain, HotAddress $hot): ?NonceValue
    {
        $this->calls++;
        return $this->probedValue === null ? null : new NonceValue($this->probedValue);
    }
}

function makeEvmChainForNonce(): Chain
{
    return Chain::register(
        id: new ChainId('ethereum-sepolia'),
        name: new ChainName('Sepolia'),
        family: ChainFamily::Evm,
        nativeCurrency: new NativeCurrency('ETH', 18),
        confirmationRequirement: new ConfirmationRequirement(12, 64),
        endpoints: [new RpcEndpoint('https://rpc.sepolia.example', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
}

function makeAllocator(?int $probedValue = null): EloquentNonceAllocator
{
    return new EloquentNonceAllocator(
        db: app(DatabaseManager::class),
        probes: new FakeProbeRegistry([
            ChainFamily::Evm->value => new FixedProbe($probedValue),
        ]),
    );
}

it('starts from 0 when probe returns null and table is empty', function (): void {
    $hot = new HotAddress('0xhotwallet1');
    $n = makeAllocator(null)->allocate(makeEvmChainForNonce(), $hot);

    expect($n->value)->toBe(0);
    expect(NonceAssignmentModel::query()
        ->where('chain_id', 'ethereum-sepolia')
        ->where('hot_address', '0xhotwallet1')
        ->count())->toBe(1);
});

it('consults the probe only when the table is empty', function (): void {
    $registry = new FakeProbeRegistry([ChainFamily::Evm->value => new FixedProbe(5)]);
    $probe = $registry->for(ChainFamily::Evm);
    $allocator = new EloquentNonceAllocator(
        db: app(DatabaseManager::class),
        probes: $registry,
    );
    $chain = makeEvmChainForNonce();
    $hot = new HotAddress('0xhotwallet2');

    expect($allocator->allocate($chain, $hot)->value)->toBe(5);   // from probe
    expect($allocator->allocate($chain, $hot)->value)->toBe(6);   // MAX+1
    expect($allocator->allocate($chain, $hot)->value)->toBe(7);

    /** @var FixedProbe $probe */
    expect($probe->calls)->toBe(1);
});

it('keeps independent counters per (chain, hot_address)', function (): void {
    $allocator = makeAllocator();
    $chain = makeEvmChainForNonce();
    $a = new HotAddress('0xhotA');
    $b = new HotAddress('0xhotB');

    expect($allocator->allocate($chain, $a)->value)->toBe(0);
    expect($allocator->allocate($chain, $a)->value)->toBe(1);
    expect($allocator->allocate($chain, $b)->value)->toBe(0);
    expect($allocator->allocate($chain, $b)->value)->toBe(1);
});

it('persists allocated_at and leaves used_at/withdrawal_id NULL', function (): void {
    makeAllocator()->allocate(makeEvmChainForNonce(), new HotAddress('0xtimes'));

    $row = NonceAssignmentModel::query()
        ->where('chain_id', 'ethereum-sepolia')
        ->where('hot_address', '0xtimes')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->nonce)->toBe(0);
    expect($row->withdrawal_id)->toBeNull();
    expect($row->used_at)->toBeNull();
    expect($row->allocated_at)->not->toBeNull();
});

it('binds EloquentNonceAllocator behind the NonceAllocator contract', function (): void {
    expect(app(NonceAllocator::class))->toBeInstanceOf(EloquentNonceAllocator::class);
});

it('enforces the composite PK (chain_id, hot_address, nonce) at DB level', function (): void {
    $row = [
        'chain_id' => 'ethereum-sepolia',
        'hot_address' => '0xunique',
        'nonce' => 0,
        'withdrawal_id' => null,
        'allocated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:sP'),
        'used_at' => null,
    ];
    NonceAssignmentModel::query()->insert($row);

    expect(fn () => NonceAssignmentModel::query()->insert($row))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
