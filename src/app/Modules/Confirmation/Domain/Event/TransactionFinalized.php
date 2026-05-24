<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Event;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

/**
 * Эмитится, когда блок tx ушёл глубже chain.max_reorg_depth и потому считается
 * reorg-безопасным. Подписчики (Ledger, Webhook) могут зафиксировать tx как
 * окончательную: дальнейшие компенсаторные действия не предполагаются.
 */
final readonly class TransactionFinalized
{
    public function __construct(
        public string $incomingTransactionId,
        public ChainId $chainId,
        public int $confirmations,
        public DateTimeImmutable $occurredAt,
    ) {}
}
