<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Dashboard\Provider;

use App\Modules\Education\Application\Contract\Dashboard\WithdrawalQueueOverviewProvider;
use App\Modules\Education\Application\DTO\Dashboard\WithdrawalQueueSection;
use App\Modules\Education\Application\DTO\Dashboard\WithdrawalRow;
use App\Modules\Education\Infrastructure\Persistence\Eloquent\WithdrawalLookupModel;
use Illuminate\Support\Facades\DB;

final readonly class EloquentWithdrawalQueueOverviewProvider implements WithdrawalQueueOverviewProvider
{
    private const RECENT_LIMIT = 20;

    public function load(): WithdrawalQueueSection
    {
        /** @var array<int, object{status:string, c:int}> $statusRows */
        $statusRows = DB::table('withdrawals')
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->get()
            ->all();

        $countsByStatus = [];
        foreach ($statusRows as $row) {
            $countsByStatus[(string) $row->status] = (int) $row->c;
        }

        $rows = WithdrawalLookupModel::query()
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        $recent = [];
        foreach ($rows as $row) {
            $recent[] = new WithdrawalRow(
                id: $row->id,
                chainId: $row->chain_id,
                status: $row->status,
                amount: $row->amount,
                currency: $row->currency,
                toAddress: $row->to_address,
                txHash: $row->tx_hash,
                confirmations: $row->confirmations,
                failureReason: $row->failure_reason,
                requestedAt: $row->requested_at->format(DATE_ATOM),
                broadcastAt: $row->broadcast_at?->format(DATE_ATOM),
                confirmedAt: $row->confirmed_at?->format(DATE_ATOM),
            );
        }

        return new WithdrawalQueueSection(
            countsByStatus: $countsByStatus,
            recent: $recent,
        );
    }
}
