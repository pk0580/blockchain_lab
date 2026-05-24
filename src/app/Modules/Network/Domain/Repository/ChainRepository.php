<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Repository;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainId;

interface ChainRepository
{
    public function findById(ChainId $id): ?Chain;

    public function existsById(ChainId $id): bool;

    public function save(Chain $chain): void;

    /**
     * @return list<Chain>
     */
    public function allEnabled(): array;

    /**
     * @return list<Chain>
     */
    public function all(): array;
}
