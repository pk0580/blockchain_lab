<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\UseCase\RecordLedgerCredit;

final readonly class RecordLedgerCreditResult
{
    public function __construct(
        public bool $created,
        public ?string $ledgerEntryId,
    ) {}
}
