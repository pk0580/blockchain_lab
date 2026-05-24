<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

/**
 * Phase 1 keeps this an empty marker contract. Phase 6 grows it with concrete
 * methods (estimate(), priorityTiers(), …) — each chain family models fees
 * differently (BTC sat/vB, EVM EIP-1559, Tron bandwidth+energy) so we defer
 * the unified shape until we have enough data to pick a good lowest common
 * denominator.
 */
interface FeeEstimator
{
}
