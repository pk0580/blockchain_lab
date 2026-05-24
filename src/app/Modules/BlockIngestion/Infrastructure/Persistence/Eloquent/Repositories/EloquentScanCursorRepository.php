<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\BlockIngestion\Domain\Repository\ScanCursorRepository;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Mappers\ScanCursorMapper;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models\ScanCursorModel;
use App\Modules\Network\Domain\ValueObject\ChainId;

final readonly class EloquentScanCursorRepository implements ScanCursorRepository
{
    public function __construct(private ScanCursorMapper $mapper) {}

    public function findByChain(ChainId $chainId): ?ScanCursor
    {
        $model = ScanCursorModel::query()->whereKey($chainId->value)->first();
        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function save(ScanCursor $cursor): void
    {
        $row = $this->mapper->toRow($cursor);
        ScanCursorModel::query()->updateOrInsert(
            ['chain_id' => $row['chain_id']],
            $row,
        );
    }
}
