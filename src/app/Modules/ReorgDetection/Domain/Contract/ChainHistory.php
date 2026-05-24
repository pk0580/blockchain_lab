<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\ReadModel\StoredBlockSummary;

/**
 * Read-port над общей таблицей `blocks`. Реализуется в
 * ReorgDetection::Infrastructure через собственную Eloquent-модель, без импорта
 * BlockIngestion::Domain.
 */
interface ChainHistory
{
    public function findByHeight(ChainId $chainId, BlockHeight $height): ?StoredBlockSummary;

    public function findByHash(ChainId $chainId, BlockHash $hash): ?StoredBlockSummary;

    public function latestHeight(ChainId $chainId): ?BlockHeight;
}
