<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

final readonly class LedgerSection
{
    /**
     * @param  array<string,int>  $countsByStatus       — `confirmed|pending|reversed` → count
     * @param  list<LedgerEntryRow>  $recentEntries     — latest first
     */
    public function __construct(
        public int $totalEntries,
        public array $countsByStatus,
        public array $recentEntries,
    ) {}

    /**
     * @return array{total_entries:int, counts_by_status:array<string,int>, recent_entries:list<array<string,mixed>>}
     */
    public function toArray(): array
    {
        return [
            'total_entries' => $this->totalEntries,
            'counts_by_status' => $this->countsByStatus,
            'recent_entries' => array_map(fn (LedgerEntryRow $r) => $r->toArray(), $this->recentEntries),
        ];
    }
}
