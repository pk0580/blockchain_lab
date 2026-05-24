<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only проекция `withdrawals` для admin-dashboard'а.
 *
 * @property string $id
 * @property string $chain_id
 * @property string $status
 * @property string $amount
 * @property string $currency
 * @property string $to_address
 * @property string|null $tx_hash
 * @property int $confirmations
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon $requested_at
 * @property \Illuminate\Support\Carbon|null $broadcast_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
final class WithdrawalLookupModel extends Model
{
    protected $table = 'withdrawals';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $casts = [
        'confirmations' => 'integer',
        'requested_at' => 'immutable_datetime',
        'broadcast_at' => 'immutable_datetime',
        'confirmed_at' => 'immutable_datetime',
    ];
}
