<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

final readonly class WithdrawalRow
{
    public function __construct(
        public string $id,
        public string $chainId,
        public string $status,
        public string $amount,
        public string $currency,
        public string $toAddress,
        public ?string $txHash,
        public int $confirmations,
        public ?string $failureReason,
        public string $requestedAt,
        public ?string $broadcastAt,
        public ?string $confirmedAt,
    ) {}

    /**
     * @return array{id:string, chain_id:string, status:string, amount:string, currency:string, to_address:string, tx_hash:?string, confirmations:int, failure_reason:?string, requested_at:string, broadcast_at:?string, confirmed_at:?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'chain_id' => $this->chainId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'to_address' => $this->toAddress,
            'tx_hash' => $this->txHash,
            'confirmations' => $this->confirmations,
            'failure_reason' => $this->failureReason,
            'requested_at' => $this->requestedAt,
            'broadcast_at' => $this->broadcastAt,
            'confirmed_at' => $this->confirmedAt,
        ];
    }
}
