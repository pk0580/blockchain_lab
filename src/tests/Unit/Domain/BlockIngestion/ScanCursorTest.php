<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

it('initialises at head height with no pending blocks', function (): void {
    $cursor = ScanCursor::initialise(
        new ChainId('bitcoin-regtest'),
        new BlockHeight(100),
        new DateTimeImmutable(),
    );

    expect($cursor->lastScannedHeight()->value)->toBe(100);
    expect($cursor->lastSeenHeadHeight()->value)->toBe(100);
    expect($cursor->hasPendingBlocks())->toBeFalse();
    expect($cursor->nextHeight()->value)->toBe(101);
});

it('reports pending blocks once the observed head moves forward', function (): void {
    $cursor = ScanCursor::initialise(new ChainId('btc'), new BlockHeight(10), new DateTimeImmutable());

    $cursor->observeHead(new BlockHeight(12), new DateTimeImmutable());
    expect($cursor->hasPendingBlocks())->toBeTrue();
    expect($cursor->lastSeenHeadHeight()->value)->toBe(12);
});

it('advances strictly by one block', function (): void {
    $cursor = ScanCursor::initialise(new ChainId('btc'), new BlockHeight(0), new DateTimeImmutable());
    $cursor->observeHead(new BlockHeight(2), new DateTimeImmutable());

    $cursor->advanceTo(new BlockHeight(1), new DateTimeImmutable());
    expect($cursor->lastScannedHeight()->value)->toBe(1);

    expect(fn () => $cursor->advanceTo(new BlockHeight(3), new DateTimeImmutable()))
        ->toThrow(\InvalidArgumentException::class);
});

it('does not regress on a shorter observed head (Phase 5 will handle reorgs)', function (): void {
    $cursor = ScanCursor::initialise(new ChainId('btc'), new BlockHeight(10), new DateTimeImmutable());
    $cursor->observeHead(new BlockHeight(5), new DateTimeImmutable());

    expect($cursor->lastScannedHeight()->value)->toBe(10);
    expect($cursor->lastSeenHeadHeight()->value)->toBe(10);
});
