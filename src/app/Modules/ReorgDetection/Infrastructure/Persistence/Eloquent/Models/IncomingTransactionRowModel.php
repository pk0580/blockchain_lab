<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Собственное view ReorgDetection поверх общей таблицы `incoming_transactions`.
 * Используется только для batch-апдейта статуса в orphaned. State-machine
 * проверки делаются в IncomingTxStatus на стороне BlockIngestion — здесь
 * массовая операция SQL UPDATE WHERE block_height = ... AND status NOT IN
 * ('finalized', 'orphaned').
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

    protected $casts = [
        'block_height' => 'integer',
        'confirmations' => 'integer',
    ];
}
