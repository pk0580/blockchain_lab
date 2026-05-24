<?php

declare(strict_types=1);

namespace App\Modules\Network\Application\UseCase\RegisterChain;

/**
 * @phpstan-type EndpointArray array{url: string, kind: string, priority?: int, weight?: int}
 */
final readonly class RegisterChainData
{
    /**
     * @param list<EndpointArray> $endpoints
     */
    public function __construct(
        public string $chainId,
        public string $name,
        public string $family,
        public string $currencySymbol,
        public int $currencyDecimals,
        public int $requiredConfirmations,
        public int $maxReorgDepth,
        public array $endpoints,
        public bool $enable = true,
    ) {}
}
