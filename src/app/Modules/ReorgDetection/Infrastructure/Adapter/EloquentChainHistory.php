<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Adapter;

use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\Contract\ChainHistory;
use App\Modules\ReorgDetection\Domain\ReadModel\StoredBlockSummary;
use App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models\BlockReadModel;

final readonly class EloquentChainHistory implements ChainHistory
{
    public function findByHeight(ChainId $chainId, BlockHeight $height): ?StoredBlockSummary
    {
        $row = BlockReadModel::query()
            ->where('chain_id', $chainId->value)
            ->where('height', $height->value)
            ->first(['chain_id', 'height', 'hash', 'parent_hash']);

        return $row === null ? null : $this->toSummary($row);
    }

    public function findByHash(ChainId $chainId, BlockHash $hash): ?StoredBlockSummary
    {
        $row = BlockReadModel::query()
            ->where('chain_id', $chainId->value)
            ->where('hash', $hash->normalized())
            ->first(['chain_id', 'height', 'hash', 'parent_hash']);

        return $row === null ? null : $this->toSummary($row);
    }

    public function latestHeight(ChainId $chainId): ?BlockHeight
    {
        $value = BlockReadModel::query()
            ->where('chain_id', $chainId->value)
            ->max('height');

        return $value === null ? null : new BlockHeight((int) $value);
    }

    private function toSummary(BlockReadModel $row): StoredBlockSummary
    {
        return new StoredBlockSummary(
            chainId: new ChainId($row->chain_id),
            height: new BlockHeight((int) $row->height),
            hash: new BlockHash($row->hash),
            parentHash: new BlockHash($row->parent_hash),
        );
    }
}
