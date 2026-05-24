<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Per-family probe lookup. Для семейств без probe (Bitcoin) возвращает null;
 * аллокатор интерпретирует это как «при пустой таблице стартуем с 0» (а в
 * Bitcoin случае allocate вообще не должен вызываться).
 */
interface NonceProbeRegistry
{
    public function for(ChainFamily $family): ?NonceProbe;
}
