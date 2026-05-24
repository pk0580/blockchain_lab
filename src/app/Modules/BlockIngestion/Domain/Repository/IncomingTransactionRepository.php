<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Repository;

use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;

interface IncomingTransactionRepository
{
    public function save(IncomingTransaction $tx): void;

    public function existsForRecipient(ChainId $chainId, TxHash $txHash, string $toAddress): bool;

    /**
     * @return list<IncomingTransaction>
     */
    public function findByChain(ChainId $chainId): array;
}
