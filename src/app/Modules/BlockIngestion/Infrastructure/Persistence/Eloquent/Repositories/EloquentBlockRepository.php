<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\BlockIngestion\Domain\Repository\BlockRepository;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Mappers\BlockMapper;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models\BlockModel;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;

final readonly class EloquentBlockRepository implements BlockRepository
{
    public function __construct(private BlockMapper $mapper) {}

    public function save(Block $block): void
    {
        $row = $this->mapper->toRow($block);
        BlockModel::query()->updateOrInsert(
            ['chain_id' => $row['chain_id'], 'height' => $row['height']],
            $row,
        );
    }

    public function findByHeight(ChainId $chainId, BlockHeight $height): ?Block
    {
        $model = BlockModel::query()
            ->where('chain_id', $chainId->value)
            ->where('height', $height->value)
            ->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findByHash(ChainId $chainId, BlockHash $hash): ?Block
    {
        $model = BlockModel::query()
            ->where('chain_id', $chainId->value)
            ->where('hash', $hash->normalized())
            ->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function latestForChain(ChainId $chainId): ?Block
    {
        $model = BlockModel::query()
            ->where('chain_id', $chainId->value)
            ->orderByDesc('height')
            ->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }
}
