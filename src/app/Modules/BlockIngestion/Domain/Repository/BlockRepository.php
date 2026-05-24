<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Repository;

use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;

interface BlockRepository
{
    public function save(Block $block): void;

    public function findByHeight(ChainId $chainId, BlockHeight $height): ?Block;

    public function findByHash(ChainId $chainId, BlockHash $hash): ?Block;

    public function latestForChain(ChainId $chainId): ?Block;
}
