<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Repository;

use App\Modules\Address\Domain\Entity\Address;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\DerivationIndex;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

interface AddressRepository
{
    public function findById(AddressId $id): ?Address;

    public function findByAddress(ChainFamily $family, string $address): ?Address;

    public function save(Address $address): void;

    /**
     * Атомарно возвращает следующий индекс деривации для пары (seedId, family).
     *
     * Реализации должны сериализовать конкурентные вызовы — "под капотом"
     * это advisory lock на пару (seed, family) + SELECT MAX скан.
     */
    public function nextDerivationIndex(HdSeedId $seedId, ChainFamily $family): DerivationIndex;
}
