<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Event;

use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;

final readonly class TransactionDetected
{
    public function __construct(
        public IncomingTransactionId $id,
        public ChainId $chainId,
        public TxHash $txHash,
        public BlockHeight $blockHeight,
        public string $toAddress,
        public Amount $amount,
        public Currency $currency,
        public DateTimeImmutable $occurredAt,
    ) {}
}
