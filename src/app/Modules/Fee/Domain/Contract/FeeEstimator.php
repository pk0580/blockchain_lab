<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\Contract;

use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Network\Domain\Entity\Chain;

/**
 * Per-family estimator. Реализации (BitcoinFeeEstimator, EvmFeeEstimator,
 * StubFeeEstimator) живут в Fee::Infrastructure и обращаются к node-RPC.
 *
 * Передаём всю {@see Chain} (а не один ChainId), чтобы у реализации был
 * доступ к её RPC endpoint'ам — это избавляет от лишнего шага «загрузить
 * Chain» в каждом адаптере.
 */
interface FeeEstimator
{
    /**
     * @throws \App\Modules\Fee\Domain\Exception\FeeEstimationFailedException
     */
    public function estimate(Chain $chain, FeePriority $priority): FeeQuote;
}
