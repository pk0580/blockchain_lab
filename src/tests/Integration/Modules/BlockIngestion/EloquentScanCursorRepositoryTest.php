<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\BlockIngestion\Domain\Repository\ScanCursorRepository;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

it('initialises and advances a cursor', function (): void {
    /** @var ScanCursorRepository $repo */
    $repo = app(ScanCursorRepository::class);
    $chainId = new ChainId('bitcoin-regtest');

    $cursor = ScanCursor::initialise($chainId, new BlockHeight(100), new DateTimeImmutable());
    $repo->save($cursor);

    $cursor->observeHead(new BlockHeight(102), new DateTimeImmutable());
    $cursor->advanceTo(new BlockHeight(101), new DateTimeImmutable());
    $repo->save($cursor);

    $loaded = $repo->findByChain($chainId);
    expect($loaded)->not->toBeNull();
    expect($loaded->lastScannedHeight()->value)->toBe(101);
    expect($loaded->lastSeenHeadHeight()->value)->toBe(102);
});
