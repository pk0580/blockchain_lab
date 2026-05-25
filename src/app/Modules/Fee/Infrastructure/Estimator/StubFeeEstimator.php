<?php

declare(strict_types=1);

namespace App\Modules\Fee\Infrastructure\Estimator;

use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Exception\UnsupportedChainFamilyException;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Network\Domain\Entity\Chain;

/**
 * Заглушка для семейств без реализации (например, Tron). Сознательно
 * бросает исключение, а не возвращает фейк: ни одна tx не должна дойти до
 * подписи, опираясь на липовый fee. Будущий TronFeeEstimator (bandwidth/energy)
 * заменит эту заглушку.
 */
final readonly class StubFeeEstimator implements FeeEstimator
{
    public function estimate(Chain $chain, FeePriority $priority): FeeQuote
    {
        throw UnsupportedChainFamilyException::forFamily($chain->family);
    }
}
