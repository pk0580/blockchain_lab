<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\BlockIngestion\Domain\Event\BlockIngested;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

it('emits BlockIngested on ingest', function (): void {
    $now = new DateTimeImmutable('2026-05-21T10:00:00Z');
    $block = Block::ingest(
        chainId: new ChainId('bitcoin-regtest'),
        height: new BlockHeight(102),
        hash: new BlockHash(str_repeat('a', 64)),
        parentHash: new BlockHash(str_repeat('b', 64)),
        timestamp: $now,
        scannedAt: $now,
    );

    $events = $block->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(BlockIngested::class);

    // pulling again yields nothing
    expect($block->pullPendingEvents())->toBe([]);
});

it('refuses identical hash and parent hash', function (): void {
    $hex = str_repeat('a', 64);
    Block::ingest(
        chainId: new ChainId('bitcoin-regtest'),
        height: new BlockHeight(1),
        hash: new BlockHash($hex),
        parentHash: new BlockHash($hex),
        timestamp: new DateTimeImmutable(),
        scannedAt: new DateTimeImmutable(),
    );
})->throws(\InvalidArgumentException::class);
