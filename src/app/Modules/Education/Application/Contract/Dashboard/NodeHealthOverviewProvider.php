<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\Contract\Dashboard;

use App\Modules\Education\Application\DTO\Dashboard\NodeHealthSection;

/**
 * Тянет последний health snapshot по всем зарегистрированным сетям и
 * проецирует их в плоский ряд endpoint-строк. Реализация ходит в
 * `NodeHealth::EndpointHealthRegistry` + `Network::ChainRepository`.
 */
interface NodeHealthOverviewProvider
{
    public function load(): NodeHealthSection;
}
