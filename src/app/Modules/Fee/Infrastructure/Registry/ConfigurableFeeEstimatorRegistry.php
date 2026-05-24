<?php

declare(strict_types=1);

namespace App\Modules\Fee\Infrastructure\Registry;

use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Contract\FeeEstimatorRegistry;
use App\Modules\Fee\Domain\Exception\UnsupportedChainFamilyException;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Маппинг ChainFamily → конкретный {@see FeeEstimator}. Инжектируется через
 * {@see \App\Modules\Fee\Infrastructure\Provider\FeeServiceProvider} —
 * там же замыкания резолвят RPC URL и timeouts из config.
 */
final readonly class ConfigurableFeeEstimatorRegistry implements FeeEstimatorRegistry
{
    /**
     * @param array<string, FeeEstimator> $estimatorsByFamily ключ — ChainFamily::value
     */
    public function __construct(private array $estimatorsByFamily) {}

    public function for(ChainFamily $family): FeeEstimator
    {
        return $this->estimatorsByFamily[$family->value]
            ?? throw UnsupportedChainFamilyException::forFamily($family);
    }
}
