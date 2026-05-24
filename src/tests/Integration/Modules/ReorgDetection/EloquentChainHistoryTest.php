<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\ReorgDetection;

use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\BlockIngestion\Domain\Repository\BlockRepository;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\Contract\ChainHistory;
use DateTimeImmutable;

function seedBlock(int $height, string $hashChar, string $parentChar): void
{
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    $repo->save(Block::ingest(
        chainId: new ChainId('bitcoin-regtest'),
        height: new BlockHeight($height),
        hash: new BlockHash(str_repeat($hashChar, 64)),
        parentHash: new BlockHash(str_repeat($parentChar, 64)),
        timestamp: (new DateTimeImmutable('2026-05-22T10:00:00Z'))->modify("+{$height} seconds"),
        scannedAt: (new DateTimeImmutable('2026-05-22T10:01:00Z'))->modify("+{$height} seconds"),
    ));
}

it('reads stored block by height', function (): void {
    seedBlock(50, 'a', 'b');

    /** @var ChainHistory $history */
    $history = app(ChainHistory::class);
    $summary = $history->findByHeight(new ChainId('bitcoin-regtest'), new BlockHeight(50));

    expect($summary)->not->toBeNull();
    expect($summary->hash->normalized())->toBe('0x'.str_repeat('a', 64));
    expect($summary->parentHash->normalized())->toBe('0x'.str_repeat('b', 64));
});

it('reads stored block by hash', function (): void {
    seedBlock(51, 'c', 'a');

    /** @var ChainHistory $history */
    $history = app(ChainHistory::class);
    $summary = $history->findByHash(
        new ChainId('bitcoin-regtest'),
        new BlockHash(str_repeat('c', 64)),
    );

    expect($summary?->height->value)->toBe(51);
});

it('returns the largest stored height', function (): void {
    seedBlock(10, '1', '0');
    seedBlock(12, '3', '2');
    seedBlock(11, '2', '1');

    /** @var ChainHistory $history */
    $history = app(ChainHistory::class);
    expect($history->latestHeight(new ChainId('bitcoin-regtest'))?->value)->toBe(12);
});

it('returns null for unknown chain', function (): void {
    /** @var ChainHistory $history */
    $history = app(ChainHistory::class);
    expect($history->latestHeight(new ChainId('unknown-chain')))->toBeNull();
});
