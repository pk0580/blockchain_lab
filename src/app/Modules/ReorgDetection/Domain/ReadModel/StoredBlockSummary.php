<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\ReadModel;

use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;

/**
 * Лёгкая read-проекция строки из общей таблицы `blocks`. ReorgDetection не
 * импортирует BlockIngestion::Domain — собственное представление + Eloquent
 * адаптер делают модуль независимым от соседа.
 */
final readonly class StoredBlockSummary
{
    public function __construct(
        public ChainId $chainId,
        public BlockHeight $height,
        public BlockHash $hash,
        public BlockHash $parentHash,
    ) {}
}
