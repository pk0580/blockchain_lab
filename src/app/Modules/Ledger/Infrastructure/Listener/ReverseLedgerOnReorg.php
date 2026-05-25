<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Listener;

use App\Modules\Ledger\Application\UseCase\ReverseLedgerForReorg\ReverseLedgerForReorgAction;
use App\Modules\Ledger\Application\UseCase\ReverseLedgerForReorg\ReverseLedgerForReorgData;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;

/**
 * Мост ReorgDetection::ReorgDetected → Ledger::ReverseLedgerForReorg.
 *
 * Реакция на реорг описана в GUIDE.md, Урок 7 «Реакция других модулей»:
 * Ledger создаёт компенсирующие проводки для всех зачислений из orphaned-диапазона.
 *
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
 * @see \GUIDE.md  Урок 8 (#урок-8--двойная-бухгалтерия-ledger)
 */
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
