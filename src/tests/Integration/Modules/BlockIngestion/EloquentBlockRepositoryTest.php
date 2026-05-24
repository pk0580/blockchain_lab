<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\BlockIngestion\Domain\Repository\BlockRepository;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

function ingestedBlock(int $height, string $hashChar, string $parentChar): Block
{
    return Block::ingest(
        chainId: new ChainId('bitcoin-regtest'),
        height: new BlockHeight($height),
        hash: new BlockHash(str_repeat($hashChar, 64)),
        parentHash: new BlockHash(str_repeat($parentChar, 64)),
        timestamp: (new DateTimeImmutable('2026-05-21T10:00:00Z'))->modify("+{$height} seconds"),
        scannedAt: (new DateTimeImmutable('2026-05-21T10:01:00Z'))->modify("+{$height} seconds"),
    );
}

it('round-trips a Block aggregate', function (): void {
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    $repo->save(ingestedBlock(102, 'a', 'b'));

    $loaded = $repo->findByHeight(new ChainId('bitcoin-regtest'), new BlockHeight(102));
    expect($loaded)->not->toBeNull();
    expect($loaded->hash->normalized())->toBe('0x'.str_repeat('a', 64));
});

it('returns null for an unknown height', function (): void {
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    expect($repo->findByHeight(new ChainId('bitcoin-regtest'), new BlockHeight(999)))->toBeNull();
});

it('finds a block by hash', function (): void {
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    $repo->save(ingestedBlock(50, 'c', 'd'));

    $loaded = $repo->findByHash(
        new ChainId('bitcoin-regtest'),
        new BlockHash(str_repeat('c', 64)),
    );

    expect($loaded)->not->toBeNull();
    expect($loaded->height->value)->toBe(50);
});

it('returns null for an unknown hash', function (): void {
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    expect($repo->findByHash(new ChainId('bitcoin-regtest'), new BlockHash(str_repeat('e', 64))))->toBeNull();
});

it('returns the block with the highest height for a chain', function (): void {
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    $repo->save(ingestedBlock(10, '1', '0'));
    $repo->save(ingestedBlock(12, '3', '2'));
    $repo->save(ingestedBlock(11, '2', '1'));

    $latest = $repo->latestForChain(new ChainId('bitcoin-regtest'));
    expect($latest)->not->toBeNull();
    expect($latest->height->value)->toBe(12);
});

it('returns null latest for an unknown chain', function (): void {
    /** @var BlockRepository $repo */
    $repo = app(BlockRepository::class);
    expect($repo->latestForChain(new ChainId('unknown-chain')))->toBeNull();
});
