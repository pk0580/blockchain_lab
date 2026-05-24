<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $wallet_id
 * @property string $chain_id
 * @property string $hot_address
 * @property string $to_address
 * @property string $amount
 * @property string $currency
 * @property string $fee_priority
 * @property array<string, scalar>|string $fee_breakdown_json
 * @property int|null $nonce
 * @property string|null $tx_hash
 * @property int $confirmations
 * @property string|null $raw_tx_hex
 * @property array<string, mixed>|string|null $signing_extras
 * @property string $status
 * @property string|null $failure_reason
 * @property string|null $replacement_of
 * @property string $idempotency_key
 * @property int $version
 * @property \Illuminate\Support\Carbon $requested_at
 * @property \Illuminate\Support\Carbon|null $broadcast_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
final class WithdrawalModel extends Model
{
    protected $table = 'withdrawals';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'wallet_id', 'chain_id', 'hot_address', 'to_address',
        'amount', 'currency',
        'fee_priority', 'fee_breakdown_json',
        'nonce', 'tx_hash', 'confirmations', 'raw_tx_hex', 'signing_extras',
        'status', 'failure_reason', 'replacement_of',
        'idempotency_key', 'version',
        'requested_at', 'broadcast_at', 'confirmed_at',
    ];

    protected $casts = [
        'fee_breakdown_json' => 'array',
        'signing_extras' => 'array',
        'nonce' => 'integer',
        'confirmations' => 'integer',
        'version' => 'integer',
        'requested_at' => 'immutable_datetime',
        'broadcast_at' => 'immutable_datetime',
        'confirmed_at' => 'immutable_datetime',
    ];
}
