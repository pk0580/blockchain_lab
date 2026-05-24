<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Снимок состояния endpoint'а в момент probe. Иммутабелен.
 *
 *  - `status` — итог probe (Healthy / Degraded / Unhealthy).
 *  - `headHeight` — текущий head, прочитанный с узла (для compare между peers).
 *  - `latencyMs` — RTT probe round-trip. null при Unhealthy.
 *  - `error` — текстовый признак сбоя при Unhealthy; null при успешных probe'ах.
 *
 * Чтобы фабрика не путалась — `EndpointObservation::healthy(int, int)`,
 * `EndpointObservation::degraded(int, int, string)`,
 * `EndpointObservation::unhealthy(string)`.
 */
final readonly class EndpointObservation
{
    public function __construct(
        public EndpointStatus $status,
        public ?int $headHeight,
        public ?int $latencyMs,
        public DateTimeImmutable $observedAt,
        public ?string $error = null,
    ) {
        if ($status === EndpointStatus::Unknown) {
            throw new InvalidArgumentException('EndpointObservation cannot carry Unknown status.');
        }
        if ($headHeight !== null && $headHeight < 0) {
            throw new InvalidArgumentException("headHeight must be >= 0, got {$headHeight}.");
        }
        if ($latencyMs !== null && $latencyMs < 0) {
            throw new InvalidArgumentException("latencyMs must be >= 0, got {$latencyMs}.");
        }
        if ($status === EndpointStatus::Unhealthy && ($error === null || $error === '')) {
            throw new InvalidArgumentException('Unhealthy observation must carry an error reason.');
        }
        if ($status !== EndpointStatus::Unhealthy && $headHeight === null) {
            throw new InvalidArgumentException(
                'Healthy / Degraded observation must carry headHeight.'
            );
        }
    }

    public static function healthy(int $headHeight, int $latencyMs, DateTimeImmutable $observedAt): self
    {
        return new self(EndpointStatus::Healthy, $headHeight, $latencyMs, $observedAt);
    }

    public static function degraded(int $headHeight, int $latencyMs, DateTimeImmutable $observedAt, string $reason): self
    {
        return new self(EndpointStatus::Degraded, $headHeight, $latencyMs, $observedAt, $reason);
    }

    public static function unhealthy(string $error, DateTimeImmutable $observedAt): self
    {
        return new self(EndpointStatus::Unhealthy, null, null, $observedAt, $error);
    }
}
