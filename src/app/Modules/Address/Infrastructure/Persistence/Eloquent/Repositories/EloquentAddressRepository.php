<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Address\Domain\Entity\Address;
use App\Modules\Address\Domain\Repository\AddressRepository;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\DerivationIndex;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Mappers\AddressMapper;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Models\AddressModel;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Illuminate\Database\DatabaseManager;

final readonly class EloquentAddressRepository implements AddressRepository
{
    public function __construct(
        private AddressMapper $mapper,
        private DatabaseManager $db,
    ) {}

    public function findById(AddressId $id): ?Address
    {
        $model = AddressModel::query()->find($id->value);
        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findByAddress(ChainFamily $family, string $address): ?Address
    {
        $model = AddressModel::query()
            ->where('family', $family->value)
            ->where('address', $address)
            ->first();
        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function save(Address $address): void
    {
        $row = $this->mapper->toRow($address);
        AddressModel::query()->updateOrInsert(['id' => $row['id']], $row);
    }

    public function nextDerivationIndex(HdSeedId $seedId, ChainFamily $family): DerivationIndex
    {
        $connection = $this->db->connection();
        $driver = $connection->getDriverName();

        // PostgreSQL: сериализация на уровне (seed, family) с помощью транзакционной
        // рекомендательной блокировки (advisory lock). SQLite (тесты): транзакции уже
        // сериализованы глобально, поэтому пустая блокировка безопасна.
        if ($driver === 'pgsql') {
            $lockKey = (string) sprintf('%u', crc32($seedId->value.':'.$family->value));
            $connection->statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
        }

        $max = AddressModel::query()
            ->where('hd_seed_id', $seedId->value)
            ->where('family', $family->value)
            ->max('derivation_index');

        $next = $max === null ? 0 : ((int) $max) + 1;

        return new DerivationIndex($next);
    }
}
