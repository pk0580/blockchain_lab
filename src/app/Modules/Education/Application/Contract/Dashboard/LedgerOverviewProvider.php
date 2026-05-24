<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\Contract\Dashboard;

use App\Modules\Education\Application\DTO\Dashboard\LedgerSection;

interface LedgerOverviewProvider
{
    public function load(): LedgerSection;
}
