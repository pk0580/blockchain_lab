<?php

declare(strict_types=1);

namespace Tests\Feature\BlockIngestion\Support;

use App\Modules\BlockIngestion\Domain\Contract\BlockSource;
use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedBlock;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\BlockHeight;

/**
 * Lets tests pre-populate a list of FetchedBlock and advertise a head height.
 * Replaces the live BitcoinCoreBlockSourceFactory in feature tests so we
 * don't need a running bitcoind container.
 */
final class FakeBlockSourceFactory implements BlockSourceFactory, BlockSource
{
    private int $head = 0;

    /** @var array<int, FetchedBlock> */
    private array $blocks = [];

    public function setHead(int $height): void
    {
        $this->head = $height;
    }

    public function pushBlock(FetchedBlock $block): void
    {
        $this->blocks[$block->height->value] = $block;
        if ($block->height->value > $this->head) {
            $this->head = $block->height->value;
        }
    }

    public function for(Chain $chain): BlockSource
    {
        return $this;
    }

    public function currentHead(): BlockHeight
    {
        return new BlockHeight($this->head);
    }

    public function fetchBlockAt(BlockHeight $height): FetchedBlock
    {
        return $this->blocks[$height->value]
            ?? throw BlockSourceException::notFound("block at height {$height->value}");
    }
}
