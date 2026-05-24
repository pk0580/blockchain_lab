<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Mappers\IncomingTransactionMapper;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionModel;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;

final readonly class EloquentIncomingTransactionRepository implements IncomingTransactionRepository
{
    public function __construct(private IncomingTransactionMapper $mapper) {}

    public function save(IncomingTransaction $tx): void
    {
        $row = $this->mapper->toRow($tx);
        IncomingTransactionModel::query()->updateOrInsert(
            ['id' => $row['id']],
            $row,
        );
    }

    public function existsForRecipient(ChainId $chainId, TxHash $txHash, string $toAddress): bool
    {
        return IncomingTransactionModel::query()
            ->where('chain_id', $chainId->value)
            ->where('tx_hash', $txHash->normalized())
            ->where('to_address', $toAddress)
            ->exists();
    }

    /**
     * @return list<IncomingTransaction>
     */
    public function findByChain(ChainId $chainId): array
    {
        return array_values(
            IncomingTransactionModel::query()
                ->where('chain_id', $chainId->value)
                ->orderBy('detected_at')
                ->get()
                ->map($this->mapper->toDomain(...))
                ->all(),
        );
    }
}
