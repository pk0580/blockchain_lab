<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $chain_id
 * @property int $height
 * @property string $hash
 * @property string $parent_hash
 * @property \Illuminate\Support\Carbon $timestamp
 * @property \Illuminate\Support\Carbon $scanned_at
 */
final class BlockModel extends Model
{
    protected $table = 'blocks';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'height';

    protected $keyType = 'int';

    protected $fillable = [
        'chain_id', 'height', 'hash', 'parent_hash', 'timestamp', 'scanned_at',
    ];

    protected $casts = [
        'height' => 'integer',
        'timestamp' => 'immutable_datetime',
        'scanned_at' => 'immutable_datetime',
    ];
}
