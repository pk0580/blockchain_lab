<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Address\Domain\Entity\HdSeed;
use App\Modules\Address\Domain\Repository\HdSeedRepository;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Mappers\HdSeedMapper;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Models\HdSeedModel;

final readonly class EloquentHdSeedRepository implements HdSeedRepository
{
    public function __construct(private HdSeedMapper $mapper) {}

    public function findById(HdSeedId $id): ?HdSeed
    {
        $model = HdSeedModel::query()->find($id->value);
        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function save(HdSeed $seed): void
    {
        $row = $this->mapper->toRow($seed);
        HdSeedModel::query()->updateOrInsert(['id' => $row['id']], $row);
    }

    /**
     * @return list<HdSeed>
     */
    public function all(): array
    {
        return array_values(
            HdSeedModel::query()->orderBy('created_at')->get()
                ->map($this->mapper->toDomain(...))->all(),
        );
    }
}
