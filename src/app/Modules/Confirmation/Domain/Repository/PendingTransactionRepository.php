<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Repository;

use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;
use App\Modules\Confirmation\Domain\ValueObject\PendingTransactionView;
use App\Modules\Network\Domain\ValueObject\ChainId;

interface PendingTransactionRepository
{
    /**
     * @return list<PendingTransactionView>
     */
    public function findPending(ChainId $chainId): array;

    public function applyOutcome(string $id, int $confirmations, ConfirmationOutcome $outcome): void;
}
