<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainId;

/**
 * Исходящий порт: запрос "как высоко поднялся сканер в этой сети?".
 * Реализовано на основе таблицы scan_cursors (принадлежит BlockIngestion), но
 * доступ осуществляется через этот контракт, чтобы Confirmation::Domain не имел
 * кросс-модульных импортов.
 *
 * Возвращает null, если курсор еще не инициализирован — вызывающая сторона трактует это
 * как "пересчет невозможен" и завершает работу без ошибок.
 */
interface ChainScannerHead
{
    public function lastScannedHeight(ChainId $chainId): ?int;
}
