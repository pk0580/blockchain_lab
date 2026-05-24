<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Repository;

use App\Modules\Address\Domain\Entity\HdSeed;
use App\Modules\Address\Domain\ValueObject\HdSeedId;

interface HdSeedRepository
{
    public function findById(HdSeedId $id): ?HdSeed;

    public function save(HdSeed $seed): void;

    /**
     * @return list<HdSeed>
     */
    public function all(): array;
}
