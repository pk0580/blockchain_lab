<?php

declare(strict_types=1);

namespace App\Modules\Network\Application\Query\ListChains;

/**
 * @phpstan-type EndpointView array{url: string, kind: string, priority: int, weight: int}
 */
final readonly class ChainView
{
    /**
     * @param list<EndpointView> $endpoints
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $family,
        public string $currencySymbol,
        public int $requiredConfirmations,
        public int $maxReorgDepth,
        public bool $enabled,
        public array $endpoints,
    ) {}
}
