<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\UseCase\ReverseLedgerForReorg;

final readonly class ReverseLedgerForReorgData
{
    public function __construct(
        public string $chainId,
        public int $fromHeight,
    ) {}
}
