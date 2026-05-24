<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\Event;

use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;
use DateTimeImmutable;

/**
 * Поднимается только когда status действительно изменился (Healthy → Unhealthy
 * или наоборот). Повторный probe с тем же status события НЕ эмитит — это
 * сохраняет события сосредоточенными на transitions, а observability на
 * `EndpointObservation::observedAt`.
 */
final readonly class EndpointHealthChanged
{
    public function __construct(
        public EndpointKey $endpoint,
        public EndpointStatus $from,
        public EndpointStatus $to,
        public DateTimeImmutable $occurredAt,
    ) {}
}
