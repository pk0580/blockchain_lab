<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\Contract\Dashboard;

use App\Modules\Education\Application\DTO\Dashboard\MempoolSection;

/**
 * Снимок mempool'а per chain. Реализация Phase 8.4 ходит только в Bitcoin
 * regtest (`getrawmempool`). Для EVM/Tron строка отдается с `txCount=null`
 * и пояснительным `error`-полем ("not exposed at this layer").
 */
interface MempoolOverviewProvider
{
    public function load(): MempoolSection;
}
