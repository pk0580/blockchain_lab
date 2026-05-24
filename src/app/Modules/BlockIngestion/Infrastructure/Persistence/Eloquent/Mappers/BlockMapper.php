<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models\BlockModel;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final class BlockMapper
{
    public function toDomain(BlockModel $row): Block
    {
        return Block::reconstitute(
            chainId: new ChainId($row->chain_id),
            height: new BlockHeight($row->height),
            hash: new BlockHash($row->hash),
            parentHash: new BlockHash($row->parent_hash),
            timestamp: DateTimeImmutable::createFromInterface($row->timestamp),
            scannedAt: DateTimeImmutable::createFromInterface($row->scanned_at),
        );
    }

    /**
     * @return array{
     *     chain_id: string, height: int, hash: string,
     *     parent_hash: string, timestamp: \DateTimeImmutable,
     *     scanned_at: \DateTimeImmutable,
     * }
     */
    public function toRow(Block $block): array
    {
        return [
            'chain_id' => $block->chainId->value,
            'height' => $block->height->value,
            'hash' => $block->hash->normalized(),
            'parent_hash' => $block->parentHash->normalized(),
            'timestamp' => $block->timestamp,
            'scanned_at' => $block->scannedAt,
        ];
    }
}
