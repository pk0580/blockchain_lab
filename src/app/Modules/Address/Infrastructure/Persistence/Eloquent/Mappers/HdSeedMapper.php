<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Address\Domain\Entity\HdSeed;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\HdSeedReference;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Models\HdSeedModel;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

final class HdSeedMapper
{
    public function toDomain(HdSeedModel $row): HdSeed
    {
        return HdSeed::reconstitute(
            id: new HdSeedId($row->id),
            reference: new HdSeedReference($row->reference),
            family: $row->family !== null ? ChainFamily::from($row->family) : null,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
        );
    }

    /**
     * @return array{id: string, reference: string, family: string|null, created_at: \DateTimeImmutable}
     */
    public function toRow(HdSeed $seed): array
    {
        return [
            'id' => $seed->id->value,
            'reference' => (string) $seed->reference,
            'family' => $seed->family?->value,
            'created_at' => $seed->createdAt,
        ];
    }
}
