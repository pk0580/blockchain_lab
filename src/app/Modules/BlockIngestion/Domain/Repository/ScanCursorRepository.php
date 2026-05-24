<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Repository;

use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\Network\Domain\ValueObject\ChainId;

interface ScanCursorRepository
{
    public function findByChain(ChainId $chainId): ?ScanCursor;

    public function save(ScanCursor $cursor): void;
}
