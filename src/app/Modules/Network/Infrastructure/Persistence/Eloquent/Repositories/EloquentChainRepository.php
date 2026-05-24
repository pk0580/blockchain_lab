<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Infrastructure\Persistence\Eloquent\Mappers\ChainMapper;
use App\Modules\Network\Infrastructure\Persistence\Eloquent\Models\ChainModel;
use App\Modules\Network\Infrastructure\Persistence\Eloquent\Models\ChainRpcEndpointModel;

final readonly class EloquentChainRepository implements ChainRepository
{
    public function __construct(private ChainMapper $mapper) {}

    public function findById(ChainId $id): ?Chain
    {
        $model = ChainModel::query()->with('endpoints')->find($id->value);

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function existsById(ChainId $id): bool
    {
        return ChainModel::query()->whereKey($id->value)->exists();
    }

    public function save(Chain $chain): void
    {
        $row = $this->mapper->toRow($chain);

        ChainModel::query()->updateOrInsert(
            ['id' => $row['id']],
            [...$row, 'updated_at' => now()],
        );

        ChainRpcEndpointModel::query()->where('chain_id', $chain->id->value)->delete();
        foreach ($this->mapper->endpointsToRows($chain) as $endpointRow) {
            ChainRpcEndpointModel::query()->create([
                ...$endpointRow,
                'chain_id' => $chain->id->value,
            ]);
        }
    }

    /**
     * @return list<Chain>
     */
    public function allEnabled(): array
    {
        return array_values(
            ChainModel::query()
                ->with('endpoints')
                ->where('enabled', true)
                ->get()
                ->map($this->mapper->toDomain(...))
                ->all(),
        );
    }

    /**
     * @return list<Chain>
     */
    public function all(): array
    {
        return array_values(
            ChainModel::query()
                ->with('endpoints')
                ->get()
                ->map($this->mapper->toDomain(...))
                ->all(),
        );
    }
}
