<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the shared `scan_cursors` table. Confirmation never
 * writes here — only inspects last_scanned_height.
 *
 * @property string $chain_id
 * @property int $last_scanned_height
 */
final class ScanCursorRowModel extends Model
{
    protected $table = 'scan_cursors';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'chain_id';

    protected $keyType = 'string';

    protected $casts = [
        'last_scanned_height' => 'integer',
    ];
}
