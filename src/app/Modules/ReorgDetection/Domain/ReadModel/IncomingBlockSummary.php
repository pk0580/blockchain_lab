<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\ReadModel;

use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;

/**
 * Только что заингесченный BlockIngestion блок, переданный в ChainComparator
 * для сопоставления со stored-цепью. Содержит ровно те поля, которые требуются
 * для проверки parent_hash; без timestamp/scanned_at, чтобы Domain не зависел
 * от деталей источника.
 */
final readonly class IncomingBlockSummary
{
    public function __construct(
        public ChainId $chainId,
        public BlockHeight $height,
        public BlockHash $hash,
        public BlockHash $parentHash,
    ) {}
}
