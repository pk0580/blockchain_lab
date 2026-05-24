<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Contract;

use App\Modules\Ledger\Domain\ReadModel\ConfirmedTransactionData;

/**
 * Read-port над `incoming_transactions`. Реализуется в Ledger::Infrastructure
 * через собственную Eloquent-модель, чтобы не импортировать BlockIngestion::Domain.
 */
interface ConfirmedTransactionView
{
    public function findById(string $incomingTransactionId): ?ConfirmedTransactionData;
}
