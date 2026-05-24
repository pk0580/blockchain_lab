<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Event;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final readonly class TransactionConfirming
{
    public function __construct(
        public string $incomingTransactionId,
        public ChainId $chainId,
        public int $confirmations,
        public DateTimeImmutable $occurredAt,
    ) {}
}
