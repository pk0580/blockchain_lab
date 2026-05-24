<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Listener;

use App\Modules\Ledger\Application\UseCase\ReverseLedgerForReorg\ReverseLedgerForReorgAction;
use App\Modules\Ledger\Application\UseCase\ReverseLedgerForReorg\ReverseLedgerForReorgData;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;

final readonly class ReverseLedgerOnReorg
{
    public function __construct(private ReverseLedgerForReorgAction $action) {}

    public function handle(ReorgDetected $event): void
    {
        $this->action->handle(new ReverseLedgerForReorgData(
            chainId: $event->chainId->value,
            fromHeight: $event->orphanedHeight->value,
        ));
    }
}
