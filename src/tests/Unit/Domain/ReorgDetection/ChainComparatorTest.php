<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReorgDetection;

use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\ReadModel\IncomingBlockSummary;
use App\Modules\ReorgDetection\Domain\ReadModel\StoredBlockSummary;
use App\Modules\ReorgDetection\Domain\Service\ChainComparator;
use App\Modules\ReorgDetection\Domain\ValueObject\ReorgKind;

function block(string $hashChar, string $parentChar, int $height): IncomingBlockSummary
{
    return new IncomingBlockSummary(
        chainId: new ChainId('bitcoin-regtest'),
        height: new BlockHeight($height),
        hash: new BlockHash(str_repeat($hashChar, 64)),
        parentHash: new BlockHash(str_repeat($parentChar, 64)),
    );
}

function stored(string $hashChar, string $parentChar, int $height): StoredBlockSummary
{
    return new StoredBlockSummary(
        chainId: new ChainId('bitcoin-regtest'),
        height: new BlockHeight($height),
        hash: new BlockHash(str_repeat($hashChar, 64)),
        parentHash: new BlockHash(str_repeat($parentChar, 64)),
    );
}

it('returns NoBaseline when there is no stored prev block', function (): void {
    $comparator = new ChainComparator();
    $analysis = $comparator->analyze(block('a', 'b', 100), null);

    expect($analysis->kind)->toBe(ReorgKind::NoBaseline);
    expect($analysis->orphanedHeight)->toBeNull();
});

it('returns CleanExtension when parent_hash matches stored prev hash', function (): void {
    $comparator = new ChainComparator();
    $analysis = $comparator->analyze(
        newBlock: block('a', 'b', 100),
        storedPrev: stored('b', 'c', 99),
    );

    expect($analysis->kind)->toBe(ReorgKind::CleanExtension);
});

it('returns Reorg with orphaned height when hashes diverge', function (): void {
    $comparator = new ChainComparator();
    $analysis = $comparator->analyze(
        newBlock: block('a', 'd', 100),
        storedPrev: stored('b', 'c', 99),
    );

    expect($analysis->kind)->toBe(ReorgKind::Reorg);
    expect($analysis->orphanedHeight?->value)->toBe(99);
});

it('rejects mismatched chains', function (): void {
    $comparator = new ChainComparator();
    $stored = new StoredBlockSummary(
        chainId: new ChainId('ethereum-sepolia'),
        height: new BlockHeight(99),
        hash: new BlockHash(str_repeat('b', 64)),
        parentHash: new BlockHash(str_repeat('c', 64)),
    );

    $comparator->analyze(block('a', 'b', 100), $stored);
})->throws(\InvalidArgumentException::class);

it('rejects stored prev at the wrong height', function (): void {
    $comparator = new ChainComparator();
    $comparator->analyze(block('a', 'b', 100), stored('b', 'c', 50));
})->throws(\InvalidArgumentException::class);
