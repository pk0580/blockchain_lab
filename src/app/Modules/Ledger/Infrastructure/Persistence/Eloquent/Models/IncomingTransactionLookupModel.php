<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Собственное read-only view Ledger'а поверх `incoming_transactions`,
 * чтобы Ledger::Domain не зависел от BlockIngestion::Domain.
 *
 * @property string $id
 * @property string $chain_id
 * @property string $tx_hash
 * @property int|null $block_height
 * @property string $to_address
 * @property string $amount
 * @property string $currency
 */
final class IncomingTransactionLookupModel extends Model
{
    protected $table = 'incoming_transactions';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $casts = [
        'block_height' => 'integer',
    ];
}
