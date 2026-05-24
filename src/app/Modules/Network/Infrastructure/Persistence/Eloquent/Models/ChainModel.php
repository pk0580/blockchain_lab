<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $family
 * @property string $currency_symbol
 * @property int $currency_decimals
 * @property int $required_confirmations
 * @property int $max_reorg_depth
 * @property bool $enabled
 * @property \Illuminate\Support\Carbon $registered_at
 */
final class ChainModel extends Model
{
    protected $table = 'chains';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'family',
        'currency_symbol', 'currency_decimals',
        'required_confirmations', 'max_reorg_depth',
        'enabled', 'registered_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'currency_decimals' => 'integer',
        'required_confirmations' => 'integer',
        'max_reorg_depth' => 'integer',
        'registered_at' => 'immutable_datetime',
    ];

    /**
     * @return HasMany<ChainRpcEndpointModel, $this>
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(ChainRpcEndpointModel::class, 'chain_id');
    }
}
