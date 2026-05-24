<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Confirmation\Domain\Repository\PendingTransactionRepository;
use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;
use App\Modules\Confirmation\Domain\ValueObject\PendingTransactionView;
use App\Modules\Confirmation\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionRowModel;
use App\Modules\Network\Domain\ValueObject\ChainId;

final readonly class EloquentPendingTransactionRepository implements PendingTransactionRepository
{
    /**
     * Возвращает все строки, которые ещё могут поменять состояние под действием
     * текущего скан-курсора: detected/confirming могут стать confirmed,
     * confirmed может уйти в finalized. Orphaned и Finalized — terminal или
     * управляются внешними модулями (ReorgDetection, BlockIngestion после re-detect).
     *
     * @return list<PendingTransactionView>
     */
    public function findPending(ChainId $chainId): array
    {
        $pendingStatuses = [
            ConfirmationOutcome::Detected->value,
            ConfirmationOutcome::Confirming->value,
            ConfirmationOutcome::Confirmed->value,
        ];

        return array_values(
            IncomingTransactionRowModel::query()
                ->where('chain_id', $chainId->value)
                ->whereIn('status', $pendingStatuses)
                ->whereNotNull('block_height')
                ->orderBy('block_height')
                ->get(['id', 'chain_id', 'block_height', 'status', 'confirmations'])
                ->map(fn (IncomingTransactionRowModel $row): PendingTransactionView => new PendingTransactionView(
                    id: $row->id,
                    chainId: new ChainId($row->chain_id),
                    blockHeight: (int) $row->block_height,
                    status: ConfirmationOutcome::from($row->status),
                    confirmations: (int) $row->confirmations,
                ))
                ->all(),
        );
    }

    public function applyOutcome(string $id, int $confirmations, ConfirmationOutcome $outcome): void
    {
        IncomingTransactionRowModel::query()
            ->whereKey($id)
            ->update([
                'status' => $outcome->value,
                'confirmations' => $confirmations,
            ]);
    }
}
