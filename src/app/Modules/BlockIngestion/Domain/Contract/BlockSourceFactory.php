<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;

/**
 * Выбирает конкретный BlockSource для заданного Chain. Позволяет сканеру оставаться
 * независимым от конкретного блокчейна, подбирая подходящее семейство RPC для каждого ID сети.
 */
interface BlockSourceFactory
{
    public function for(Chain $chain): BlockSource;
}
