<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $hd_seed_id
 * @property string $family
 * @property string $address
 * @property string $derivation_path
 * @property int $derivation_index
 * @property string|null $wallet_id
 * @property \Illuminate\Support\Carbon $created_at
 */
final class AddressModel extends Model
{
    protected $table = 'addresses';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'hd_seed_id', 'family', 'address',
        'derivation_path', 'derivation_index', 'wallet_id', 'created_at',
    ];

    protected $casts = [
        'derivation_index' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<HdSeedModel, $this>
     */
    public function seed(): BelongsTo
    {
        return $this->belongsTo(HdSeedModel::class, 'hd_seed_id');
    }
}
