<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Adapter;

use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\Contract\ReorgWriter;
use App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models\BlockReadModel;
use App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionRowModel;
use App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models\ScanCursorRowModel;
use DateTimeImmutable;

/**
 * Adapter ReorgWriter поверх общих таблиц. Импорт IncomingTxStatus
 * допускается потому, что Infrastructure-слою разрешено читать VO соседних
 * модулей — это anti-corruption layer; запрет действует только на Domain и
 * Application.
 */
final readonly class EloquentReorgWriter implements ReorgWriter
{
    /**
     * Список статусов, поверх которых reorg может ставить Orphaned.
     * Finalized исключаем — туда не должен дотягиваться обычный reorg
     * (если дотянулся — отдельный ReorgTooDeep-флоу).
     *
     * @return list<string>
     */
    private function nonTerminalStatuses(): array
    {
        return [
            IncomingTxStatus::Detected->value,
            IncomingTxStatus::Confirming->value,
            IncomingTxStatus::Confirmed->value,
        ];
    }

    public function orphanIncomingAtHeight(ChainId $chainId, BlockHeight $height): int
    {
        return IncomingTransactionRowModel::query()
            ->where('chain_id', $chainId->value)
            ->where('block_height', $height->value)
            ->whereIn('status', $this->nonTerminalStatuses())
            ->update([
                'status' => IncomingTxStatus::Orphaned->value,
                'confirmations' => 0,
            ]);
    }

    public function deleteBlockAtHeight(ChainId $chainId, BlockHeight $height): void
    {
        BlockReadModel::query()
            ->where('chain_id', $chainId->value)
            ->where('height', $height->value)
            ->delete();
    }

    public function rollbackScanCursorTo(ChainId $chainId, BlockHeight $height): void
    {
        ScanCursorRowModel::query()
            ->where('chain_id', $chainId->value)
            ->update([
                'last_scanned_height' => $height->value,
                'updated_at' => new DateTimeImmutable(),
            ]);
    }
}
