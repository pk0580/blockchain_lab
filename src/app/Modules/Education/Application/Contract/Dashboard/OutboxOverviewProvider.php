<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\Contract\Dashboard;

use App\Modules\Education\Application\DTO\Dashboard\OutboxSection;

interface OutboxOverviewProvider
{
    public function load(): OutboxSection;
}
