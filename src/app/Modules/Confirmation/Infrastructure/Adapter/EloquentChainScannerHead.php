<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Infrastructure\Adapter;

use App\Modules\Confirmation\Domain\Contract\ChainScannerHead;
use App\Modules\Confirmation\Infrastructure\Persistence\Eloquent\Models\ScanCursorRowModel;
use App\Modules\Network\Domain\ValueObject\ChainId;

final readonly class EloquentChainScannerHead implements ChainScannerHead
{
    public function lastScannedHeight(ChainId $chainId): ?int
    {
        $row = ScanCursorRowModel::query()->whereKey($chainId->value)->first(['last_scanned_height']);
        return $row === null ? null : (int) $row->last_scanned_height;
    }
}
