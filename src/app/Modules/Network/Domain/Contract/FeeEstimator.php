<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

/**
 * Empty marker contract. A unified shape (estimate(), priorityTiers(), …) is
 * deliberately not defined here yet: each chain family models fees differently
 * (BTC sat/vB, EVM EIP-1559, Tron bandwidth+energy). The concrete oracle lives
 * in the Fee module — see {@see \App\Modules\Fee\Domain\Contract\FeeEstimator}.
 */
interface FeeEstimator
{
}
