<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;

/**
 * Хранилище последних наблюдений по endpoint'ам. Phase 7.1 хранит в Cache
 * (Redis prod / array tests). Контракт абстрактный — Phase 9 заменит на PG-таблицу
 * для исторического трендового анализа без правок Application.
 *
 * Запись `record()` идемпотентна: повторный probe c тем же observation просто
 * обновит timestamp.
 */
interface EndpointHealthRegistry
{
    public function status(EndpointKey $key): EndpointStatus;

    public function lastObservation(EndpointKey $key): ?EndpointObservation;

    public function record(EndpointKey $key, EndpointObservation $observation): void;

    /**
     * @return list<EndpointKey>
     */
    public function knownEndpointsFor(ChainId $chainId): array;
}
