<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainId;

/**
 * Lightweight read-side projection of a single row from incoming_transactions
 * that is still in a non-terminal state. Confirmation does not own the table
 * — it only updates a few columns through its repository port.
 */
final readonly class PendingTransactionView
{
    public function __construct(
        public string $id,
        public ChainId $chainId,
        public int $blockHeight,
        public ConfirmationOutcome $status,
        public int $confirmations,
    ) {}
}
