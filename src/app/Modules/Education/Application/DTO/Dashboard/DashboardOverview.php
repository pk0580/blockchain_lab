<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

/**
 * Композит из 5 секций — то, что отдается UI как единый props payload.
 * `generatedAt` нужен для UI badge'а «обновлено N сек назад».
 */
final readonly class DashboardOverview
{
    public function __construct(
        public NodeHealthSection $nodeHealth,
        public MempoolSection $mempool,
        public LedgerSection $ledger,
        public WithdrawalQueueSection $withdrawals,
        public OutboxSection $outbox,
        public string $generatedAt,
    ) {}

    /**
     * @return array{
     *   node_health: array{rows: list<array<string,mixed>>},
     *   mempool: array{rows: list<array<string,mixed>>},
     *   ledger: array{total_entries:int, counts_by_status:array<string,int>, recent_entries:list<array<string,mixed>>},
     *   withdrawals: array{counts_by_status:array<string,int>, recent:list<array<string,mixed>>},
     *   outbox: array{unpublished_messages:int, oldest_unpublished_at:?string, deliveries_pending:int, deliveries_failed:int, deliveries_delivered:int},
     *   generated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'node_health' => $this->nodeHealth->toArray(),
            'mempool' => $this->mempool->toArray(),
            'ledger' => $this->ledger->toArray(),
            'withdrawals' => $this->withdrawals->toArray(),
            'outbox' => $this->outbox->toArray(),
            'generated_at' => $this->generatedAt,
        ];
    }
}
