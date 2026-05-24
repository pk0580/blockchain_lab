<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainId;

/**
 * Boolean «эта chain поставлена на паузу для исходящих withdrawal'ов».
 * Сохраняется в Redis с TTL (Withdrawal::Infrastructure\Pause\RedisChainPauseRegistry),
 * чтобы operator clearance не требовал ручного снятия — TTL восстановит автоматически.
 *
 * Узкий контракт: Domain ничего не знает про Redis, ключи, TTL. Подгрузить новый
 * адаптер (Cache, DB) можно не трогая Application.
 */
interface ChainPauseRegistry
{
    public function isPaused(ChainId $chainId): bool;

    /**
     * @param int $ttlSeconds сколько секунд держать паузу. После — автоматически снимается.
     */
    public function pause(ChainId $chainId, int $ttlSeconds): void;

    public function clear(ChainId $chainId): void;
}
