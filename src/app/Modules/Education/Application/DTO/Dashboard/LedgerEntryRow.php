<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

final readonly class LedgerEntryRow
{
    public function __construct(
        public string $id,
        public string $walletId,
        public string $chainId,
        public string $direction,
        public string $amount,
        public string $currency,
        public string $operationType,
        public string $status,
        public ?string $relatedTxHash,
        public string $createdAt,
    ) {}

    /**
     * @return array{id:string, wallet_id:string, chain_id:string, direction:string, amount:string, currency:string, operation_type:string, status:string, related_tx_hash:?string, created_at:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->walletId,
            'chain_id' => $this->chainId,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'operation_type' => $this->operationType,
            'status' => $this->status,
            'related_tx_hash' => $this->relatedTxHash,
            'created_at' => $this->createdAt,
        ];
    }
}
