<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $chain_id
 * @property string $tx_hash
 * @property int|null $block_height
 * @property string|null $block_hash
 * @property string|null $from_address
 * @property string $to_address
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property int $confirmations
 * @property \Illuminate\Support\Carbon $detected_at
 */
final class IncomingTransactionModel extends Model
{
    protected $table = 'incoming_transactions';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'chain_id', 'tx_hash', 'block_height', 'block_hash',
        'from_address', 'to_address', 'amount', 'currency',
        'status', 'confirmations', 'detected_at',
    ];

    protected $casts = [
        'block_height' => 'integer',
        'confirmations' => 'integer',
        'detected_at' => 'immutable_datetime',
    ];
}
