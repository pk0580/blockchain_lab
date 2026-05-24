<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\UseCase\LoadDashboardOverview;

use App\Modules\Education\Application\Contract\Dashboard\LedgerOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\MempoolOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\NodeHealthOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\OutboxOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\WithdrawalQueueOverviewProvider;
use App\Modules\Education\Application\DTO\Dashboard\DashboardOverview;
use DateTimeImmutable;

/**
 * Собирает 5 read-сегментов dashboard'а в один payload. Поставщики
 * абстрактны (порт-контракты), вся cross-module зависимость локализована
 * в Education::Infrastructure.
 *
 * Здесь намеренно нет кеширования / агрегации — это admin tool, частота
 * запросов невелика (5s polling от UI). Если станет горячо — обернем в
 * Cache::remember с TTL 2-3 сек.
 */
final readonly class LoadDashboardOverviewAction
{
    public function __construct(
        private NodeHealthOverviewProvider $nodeHealth,
        private MempoolOverviewProvider $mempool,
        private LedgerOverviewProvider $ledger,
        private WithdrawalQueueOverviewProvider $withdrawals,
        private OutboxOverviewProvider $outbox,
    ) {}

    public function handle(DateTimeImmutable $now): DashboardOverview
    {
        return new DashboardOverview(
            nodeHealth: $this->nodeHealth->load(),
            mempool: $this->mempool->load(),
            ledger: $this->ledger->load(),
            withdrawals: $this->withdrawals->load(),
            outbox: $this->outbox->load(),
            generatedAt: $now->format(DATE_ATOM),
        );
    }
}
