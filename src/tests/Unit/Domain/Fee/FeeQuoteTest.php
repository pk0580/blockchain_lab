<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fee;

use App\Modules\Fee\Domain\ValueObject\BitcoinFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

it('is a readonly snapshot of chain, priority, breakdown and time', function (): void {
    expect(FeeQuote::class)->toBeReadonly();

    $q = new FeeQuote(
        chainId: new ChainId('bitcoin-regtest'),
        priority: FeePriority::Standard,
        breakdown: new BitcoinFeeBreakdown(5),
        estimatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );

    expect($q->chainId->value)->toBe('bitcoin-regtest');
    expect($q->priority)->toBe(FeePriority::Standard);
    expect($q->breakdown->family()->value)->toBe('bitcoin');
});
