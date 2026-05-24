<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $chain_id
 * @property string $url
 * @property string $kind
 * @property int $priority
 * @property int $weight
 */
final class ChainRpcEndpointModel extends Model
{
    protected $table = 'chain_rpc_endpoints';

    protected $fillable = ['chain_id', 'url', 'kind', 'priority', 'weight'];

    protected $casts = [
        'priority' => 'integer',
        'weight' => 'integer',
    ];

    /**
     * @return BelongsTo<ChainModel, $this>
     */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(ChainModel::class, 'chain_id');
    }
}
