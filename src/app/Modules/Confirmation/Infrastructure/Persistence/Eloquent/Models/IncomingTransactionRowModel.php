<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Confirmation-owned view of the shared `incoming_transactions` table.
 * Lives in this module so Confirmation::Infrastructure has no import of
 * BlockIngestion::Infrastructure. The table is created by BlockIngestion's
 * migration; this model only reads & updates a few columns.
 *
 * @property string $id
 * @property string $chain_id
 * @property int|null $block_height
 * @property string $status
 * @property int $confirmations
 */
final class IncomingTransactionRowModel extends Model
{
    protected $table = 'incoming_transactions';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'status', 'confirmations'];

    protected $casts = [
        'block_height' => 'integer',
        'confirmations' => 'integer',
    ];
}
