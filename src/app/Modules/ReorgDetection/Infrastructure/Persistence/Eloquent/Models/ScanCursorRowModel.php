<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Собственное view ReorgDetection поверх `scan_cursors`. Нужно только для
 * rollback-операции при reorg; повседневным обновлением курсора владеет
 * BlockIngestion.
 *
 * @property string $chain_id
 * @property int $last_scanned_height
 * @property int $last_seen_head_height
 */
final class ScanCursorRowModel extends Model
{
    protected $table = 'scan_cursors';

    protected $primaryKey = 'chain_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $casts = [
        'last_scanned_height' => 'integer',
        'last_seen_head_height' => 'integer',
    ];
}
