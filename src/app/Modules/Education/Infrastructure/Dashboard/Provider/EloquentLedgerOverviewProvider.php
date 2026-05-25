<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Dashboard\Provider;

use App\Modules\Education\Application\Contract\Dashboard\LedgerOverviewProvider;
use App\Modules\Education\Application\DTO\Dashboard\LedgerEntryRow;
use App\Modules\Education\Application\DTO\Dashboard\LedgerSection;
use App\Modules\Education\Infrastructure\Persistence\Eloquent\LedgerEntryLookupModel;
use Illuminate\Support\Facades\DB;

/**
 * Лента последних 20 ledger entries + breakdown по статусам. count(*) дешёвый
 * пока таблица маленькая — для production стоит добавить materialized view.
 */
final readonly class EloquentLedgerOverviewProvider implements LedgerOverviewProvider
{
    private const RECENT_LIMIT = 20;

    public function load(): LedgerSection
    {
        $totalEntries = LedgerEntryLookupModel::query()->count();

        /** @var array<int, object{status:string, c:int}> $statusRows */
        $statusRows = DB::table('ledger_entries')
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->get()
            ->all();

        $countsByStatus = [];
        foreach ($statusRows as $row) {
            $countsByStatus[(string) $row->status] = (int) $row->c;
        }

        $recent = [];
        $rows = LedgerEntryLookupModel::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        foreach ($rows as $row) {
            $recent[] = new LedgerEntryRow(
                id: $row->id,
                walletId: $row->wallet_id,
                chainId: $row->chain_id,
                direction: $row->direction,
                amount: $row->amount,
                currency: $row->currency,
                operationType: $row->operation_type,
                status: $row->status,
                relatedTxHash: $row->related_tx_hash,
                createdAt: $row->created_at->format(DATE_ATOM),
            );
        }

        return new LedgerSection(
            totalEntries: $totalEntries,
            countsByStatus: $countsByStatus,
            recentEntries: $recent,
        );
    }
}
