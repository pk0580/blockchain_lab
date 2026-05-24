<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $chain_id
 * @property int $last_scanned_height
 * @property int $last_seen_head_height
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class ScanCursorModel extends Model
{
    protected $table = 'scan_cursors';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'chain_id';

    protected $keyType = 'string';

    protected $fillable = [
        'chain_id', 'last_scanned_height', 'last_seen_head_height', 'updated_at',
    ];

    protected $casts = [
        'last_scanned_height' => 'integer',
        'last_seen_head_height' => 'integer',
        'updated_at' => 'immutable_datetime',
    ];
}
