<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints;

final readonly class ProbeChainEndpointsResult
{
    /**
     * @param list<string> $probed список URL, которые удалось опросить
     */
    public function __construct(
        public string $chainId,
        public array $probed,
        public int $statusChanges,
    ) {}
}
