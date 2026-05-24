<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;

/**
 * Точечный write-port для компенсаторных действий при reorg. Реализация в
 * Infrastructure обновляет общие таблицы (`incoming_transactions`, `blocks`,
 * `scan_cursors`) через собственные Eloquent-модели — но без ссылок на
 * BlockIngestion::Domain.
 *
 * Все методы предполагается вызывать внутри одной транзакции (открывается в
 * Application-слое), порядок: orphan → delete → rollback.
 */
interface ReorgWriter
{
    /**
     * Перевести все incoming-транзакции на указанной высоте в статус Orphaned.
     * Применяется только к строкам, которые могут стать orphan — Finalized не
     * меняется (deep reorg идёт по отдельному ReorgTooDeep-флоу).
     */
    public function orphanIncomingAtHeight(ChainId $chainId, BlockHeight $height): int;

    /**
     * Удалить запись о блоке указанной высоты (он принадлежал старой цепи).
     */
    public function deleteBlockAtHeight(ChainId $chainId, BlockHeight $height): void;

    /**
     * Откатить scan-курсор на указанную высоту. lastScannedHeight = $height,
     * lastSeenHeadHeight оставляется без изменения, если он выше.
     */
    public function rollbackScanCursorTo(ChainId $chainId, BlockHeight $height): void;
}
