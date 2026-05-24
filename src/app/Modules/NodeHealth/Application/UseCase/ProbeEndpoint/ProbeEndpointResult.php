<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\UseCase\ProbeEndpoint;

use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;

final readonly class ProbeEndpointResult
{
    public function __construct(
        public EndpointStatus $previous,
        public EndpointStatus $current,
        public ?int $headHeight,
        public ?int $latencyMs,
    ) {}

    public function statusChanged(): bool
    {
        return $this->previous !== $this->current;
    }
}
