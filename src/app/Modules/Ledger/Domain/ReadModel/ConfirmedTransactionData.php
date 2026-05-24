<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ReadModel;

use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;

/**
 * Read-проекция строки `incoming_transactions`, нужная Ledger'у для записи
 * credit-проводки. Содержит только поля, требуемые на создание LedgerEntry:
 * получатель (для резолва walletId), сумма, идентификаторы.
 */
final readonly class ConfirmedTransactionData
{
    public function __construct(
        public string $incomingTransactionId,
        public ChainId $chainId,
        public ChainFamily $family,
        public TxHash $txHash,
        public int $blockHeight,
        public string $toAddress,
        public Money $money,
    ) {}
}
