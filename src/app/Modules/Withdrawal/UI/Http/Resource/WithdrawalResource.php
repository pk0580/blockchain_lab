<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\UI\Http\Resource;

use App\Modules\Withdrawal\Domain\Entity\Withdrawal;

/**
 * Простой маппер aggregate → JSON-массив. Не используем JsonResource, потому
 * что наш контракт — Domain Entity, не Eloquent Model.
 */
final readonly class WithdrawalResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Withdrawal $w): array
    {
        return [
            'id' => $w->id->value,
            'wallet_id' => $w->walletId->value,
            'chain_id' => $w->chainId->value,
            'hot_address' => $w->hotAddress->value,
            'to_address' => $w->toAddress->value,
            'amount' => $w->amount->value,
            'currency' => $w->currency->value,
            'status' => $w->status()->value,
            'fee' => [
                'priority' => $w->feeQuote->priority,
                'breakdown' => $w->feeQuote->breakdown,
                'estimated_at' => $w->feeQuote->estimatedAt->format(DATE_ATOM),
            ],
            'nonce' => $w->nonce()?->value,
            'tx_hash' => $w->txHash()?->value,
            'confirmations' => $w->confirmations(),
            'replacement_of' => $w->replacementOf()?->value,
            'failure_reason' => $w->failureReason(),
            'requested_at' => $w->requestedAt->format(DATE_ATOM),
            'broadcast_at' => $w->broadcastAt()?->format(DATE_ATOM),
            'confirmed_at' => $w->confirmedAt()?->format(DATE_ATOM),
        ];
    }
}
