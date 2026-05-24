<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $wallet_id
 * @property string $chain_id
 * @property string $direction
 * @property string $amount
 * @property string $currency
 * @property string $operation_type
 * @property string $operation_ref
 * @property string|null $related_tx_hash
 * @property int|null $block_height
 * @property string|null $reverses_entry_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 */
final class LedgerEntryModel extends Model
{
    protected $table = 'ledger_entries';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'wallet_id', 'chain_id', 'direction', 'amount', 'currency',
        'operation_type', 'operation_ref', 'related_tx_hash', 'block_height',
        'reverses_entry_id', 'status', 'created_at',
    ];

    protected $casts = [
        'block_height' => 'integer',
        'created_at' => 'immutable_datetime',
    ];
}
