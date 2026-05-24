<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\UseCase\RecordLedgerCredit;

final readonly class RecordLedgerCreditData
{
    public function __construct(
        public string $incomingTransactionId,
    ) {}
}
