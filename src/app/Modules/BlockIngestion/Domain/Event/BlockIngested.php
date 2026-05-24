<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Event;

use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final readonly class BlockIngested
{
    public function __construct(
        public ChainId $chainId,
        public BlockHeight $height,
        public BlockHash $hash,
        public BlockHash $parentHash,
        public DateTimeImmutable $occurredAt,
    ) {}
}
