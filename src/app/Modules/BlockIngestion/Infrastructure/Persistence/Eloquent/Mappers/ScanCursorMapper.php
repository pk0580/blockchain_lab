<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models\ScanCursorModel;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final class ScanCursorMapper
{
    public function toDomain(ScanCursorModel $row): ScanCursor
    {
        return ScanCursor::reconstitute(
            chainId: new ChainId($row->chain_id),
            lastScannedHeight: new BlockHeight((int) $row->last_scanned_height),
            lastSeenHeadHeight: new BlockHeight((int) $row->last_seen_head_height),
            updatedAt: DateTimeImmutable::createFromInterface($row->updated_at),
        );
    }

    /**
     * @return array{
     *     chain_id: string, last_scanned_height: int,
     *     last_seen_head_height: int, updated_at: \DateTimeImmutable,
     * }
     */
    public function toRow(ScanCursor $cursor): array
    {
        return [
            'chain_id' => $cursor->chainId->value,
            'last_scanned_height' => $cursor->lastScannedHeight()->value,
            'last_seen_head_height' => $cursor->lastSeenHeadHeight()->value,
            'updated_at' => $cursor->updatedAt(),
        ];
    }
}
