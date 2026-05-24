<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Contract;

use App\Modules\BlockIngestion\Domain\ReadModel\FetchedBlock;
use App\Modules\Network\Domain\ValueObject\BlockHeight;

/**
 * Исходящий порт, используемый уровнем приложения для получения блоков из блокчейна.
 * Одна реализация на семейство блокчейнов живет в Инфраструктуре (Фаза 4 поставляет
 * BitcoinCoreBlockSource для семейства Bitcoin; EVM/Tron появятся в Фазе 4.5+).
 *
 * Реализации ДОЛЖНЫ переводить ошибки уровня адаптера в
 * {@see \App\Modules\BlockIngestion\Domain\Exception\BlockSourceException}.
 */
interface BlockSource
{
    public function currentHead(): BlockHeight;

    public function fetchBlockAt(BlockHeight $height): FetchedBlock;
}
