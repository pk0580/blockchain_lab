<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Fee;

use App\Modules\Fee\Application\UseCase\EstimateFee\EstimateFeeAction;
use App\Modules\Fee\Application\UseCase\EstimateFee\EstimateFeeData;
use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Contract\FeeEstimatorRegistry;
use App\Modules\Fee\Domain\Exception\UnsupportedChainFamilyException;
use App\Modules\Fee\Domain\ValueObject\BitcoinFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;

/**
 * In-memory stub: capture invocations + return a canned quote.
 */
final class StubFeeEstimator implements FeeEstimator
{
    public ?Chain $lastChain = null;

    public ?FeePriority $lastPriority = null;

    public function __construct(private readonly FeeQuote $fixedQuote) {}

    public function estimate(Chain $chain, FeePriority $priority): FeeQuote
    {
        $this->lastChain = $chain;
        $this->lastPriority = $priority;
        return $this->fixedQuote;
    }
}

final class StubRegistry implements FeeEstimatorRegistry
{
    /** @param array<string, FeeEstimator> $byFamily */
    public function __construct(private array $byFamily) {}

    public function for(ChainFamily $family): FeeEstimator
    {
        return $this->byFamily[$family->value]
            ?? throw UnsupportedChainFamilyException::forFamily($family);
    }
}

it('loads the chain, picks the per-family estimator, returns the quote', function (): void {
    /** @var ChainRepository $repo */
    $repo = app(ChainRepository::class);
    $chain = Chain::register(
        id: new ChainId('bitcoin-regtest'),
        name: new ChainName('BTC Regtest'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(1, 6),
        endpoints: [new RpcEndpoint('http://bitcoin-regtest:18443', RpcKind::Http)],
        now: new DateTimeImmutable(),
    );
    $repo->save($chain);

    $expectedQuote = new FeeQuote(
        chainId: new ChainId('bitcoin-regtest'),
        priority: FeePriority::High,
        breakdown: new BitcoinFeeBreakdown(42),
        estimatedAt: new DateTimeImmutable(),
    );
    $stub = new StubFeeEstimator($expectedQuote);
    $registry = new StubRegistry([ChainFamily::Bitcoin->value => $stub]);

    $action = new EstimateFeeAction($repo, $registry);
    $result = $action->handle(new EstimateFeeData(
        chainId: new ChainId('bitcoin-regtest'),
        priority: FeePriority::High,
    ));

    expect($result->quote)->toBe($expectedQuote);
    expect($stub->lastChain?->id->value)->toBe('bitcoin-regtest');
    expect($stub->lastPriority)->toBe(FeePriority::High);
});

it('throws ChainNotFoundException for an unknown chain id', function (): void {
    $action = new EstimateFeeAction(
        app(ChainRepository::class),
        new StubRegistry([]),
    );

    $action->handle(new EstimateFeeData(
        chainId: new ChainId('does-not-exist'),
        priority: FeePriority::Standard,
    ));
})->throws(ChainNotFoundException::class);
