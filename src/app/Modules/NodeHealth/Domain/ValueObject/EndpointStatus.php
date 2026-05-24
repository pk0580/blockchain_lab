<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\ValueObject;

/**
 * Состояние RPC endpoint'а в момент последнего probe.
 *
 *  - `Unknown` — узел ещё не опрашивался (cold start). Picker трактует как
 *    candidate (better-than-nothing).
 *  - `Healthy` — последний probe прошёл, head не lag'ает за peer'ами.
 *  - `Degraded` — отвечает, но slow/lagged. Picker предпочтёт Healthy, но
 *    использует Degraded если ничего получше нет.
 *  - `Unhealthy` — последний probe упал. Picker полностью пропускает.
 *
 * Логика «через сколько неудач переходим в Unhealthy» — в `EndpointHealthRegistry`.
 * Phase 7.1 переключает по single observation; Phase 9+ добавит N-of-M плавающее окно.
 */
enum EndpointStatus: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';

    public function isUsable(): bool
    {
        return match ($this) {
            self::Healthy, self::Degraded, self::Unknown => true,
            self::Unhealthy => false,
        };
    }
}
