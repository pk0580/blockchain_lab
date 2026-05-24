<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Диспетчер: по семейству сети возвращает соответствующий {@see FeeEstimator}.
 * Реализация по умолчанию — {@see \App\Modules\Fee\Infrastructure\Registry\ConfigurableFeeEstimatorRegistry}.
 * Тесты могут регистрировать `InMemoryFeeEstimatorRegistry` со stub'ом.
 */
interface FeeEstimatorRegistry
{
    /**
     * @throws \App\Modules\Fee\Domain\Exception\UnsupportedChainFamilyException
     */
    public function for(ChainFamily $family): FeeEstimator;
}
