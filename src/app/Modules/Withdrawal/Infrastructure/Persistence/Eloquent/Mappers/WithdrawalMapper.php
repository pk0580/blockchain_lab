<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models\WithdrawalModel;
use DateTimeImmutable;

final class WithdrawalMapper
{
    public function toDomain(WithdrawalModel $row): Withdrawal
    {
        /** @var array<string, scalar> $breakdown */
        $breakdown = is_array($row->fee_breakdown_json)
            ? $row->fee_breakdown_json
            : (array) json_decode((string) $row->fee_breakdown_json, true);

        $extras = null;
        if (is_array($row->signing_extras)) {
            /** @var array<string, mixed> $extras */
            $extras = $row->signing_extras;
        } elseif (is_string($row->signing_extras) && $row->signing_extras !== '') {
            /** @var array<string, mixed> $extras */
            $extras = (array) json_decode($row->signing_extras, true);
        }

        return Withdrawal::reconstitute(
            id: new WithdrawalId($row->id),
            walletId: new WalletId($row->wallet_id),
            chainId: new ChainId($row->chain_id),
            hotAddress: new HotAddress($row->hot_address),
            toAddress: new Address($row->to_address),
            amount: new WithdrawalAmount($row->amount),
            currency: new Currency($row->currency),
            feeQuote: new FeeQuoteSnapshot(
                priority: $row->fee_priority,
                breakdown: $breakdown,
                estimatedAt: DateTimeImmutable::createFromInterface($row->requested_at),
            ),
            idempotencyKey: new IdempotencyKey($row->idempotency_key),
            requestedAt: DateTimeImmutable::createFromInterface($row->requested_at),
            status: WithdrawalStatus::from($row->status),
            nonce: $row->nonce !== null ? new NonceValue($row->nonce) : null,
            rawTxHex: $row->raw_tx_hex,
            signingExtras: $extras,
            txHash: $row->tx_hash !== null && $row->tx_hash !== '' ? new TxHash($row->tx_hash) : null,
            broadcastAt: $row->broadcast_at !== null
                ? DateTimeImmutable::createFromInterface($row->broadcast_at)
                : null,
            confirmedAt: $row->confirmed_at !== null
                ? DateTimeImmutable::createFromInterface($row->confirmed_at)
                : null,
            confirmations: $row->confirmations,
            replacementOf: $row->replacement_of !== null && $row->replacement_of !== ''
                ? new WithdrawalId($row->replacement_of)
                : null,
            failureReason: $row->failure_reason,
            version: $row->version,
        );
    }

    /**
     * @return array{
     *     id: string, wallet_id: string, chain_id: string, hot_address: string,
     *     to_address: string, amount: string, currency: string,
     *     fee_priority: string, fee_breakdown_json: string,
     *     nonce: int|null, tx_hash: string|null, confirmations: int,
     *     raw_tx_hex: string|null, signing_extras: string|null,
     *     status: string, failure_reason: string|null, replacement_of: string|null,
     *     idempotency_key: string, version: int,
     *     requested_at: \DateTimeImmutable,
     *     broadcast_at: \DateTimeImmutable|null,
     *     confirmed_at: \DateTimeImmutable|null,
     * }
     */
    public function toRow(Withdrawal $w): array
    {
        $extras = $w->signingExtras();

        return [
            'id' => $w->id->value,
            'wallet_id' => $w->walletId->value,
            'chain_id' => $w->chainId->value,
            'hot_address' => $w->hotAddress->value,
            'to_address' => $w->toAddress->value,
            'amount' => $w->amount->value,
            'currency' => $w->currency->value,
            'fee_priority' => $w->feeQuote->priority,
            'fee_breakdown_json' => (string) json_encode($w->feeQuote->breakdown, JSON_THROW_ON_ERROR),
            'nonce' => $w->nonce()?->value,
            'tx_hash' => $w->txHash()?->value,
            'confirmations' => $w->confirmations(),
            'raw_tx_hex' => $w->rawTxHex(),
            'signing_extras' => $extras !== null
                ? (string) json_encode($extras, JSON_THROW_ON_ERROR)
                : null,
            'status' => $w->status()->value,
            'failure_reason' => $w->failureReason(),
            'replacement_of' => $w->replacementOf()?->value,
            'idempotency_key' => $w->idempotencyKey->value,
            'version' => $w->version(),
            'requested_at' => $w->requestedAt,
            'broadcast_at' => $w->broadcastAt(),
            'confirmed_at' => $w->confirmedAt(),
        ];
    }
}
