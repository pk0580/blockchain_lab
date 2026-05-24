<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainId;

interface ChainAdapterRegistry
{
    /**
     * @throws \App\Modules\Network\Domain\Exception\ChainNotFoundException
     */
    public function adapterFor(ChainId $chainId): ChainAdapter;

    /**
     * @return list<ChainAdapter>
     */
    public function enabledAdapters(): array;
}
