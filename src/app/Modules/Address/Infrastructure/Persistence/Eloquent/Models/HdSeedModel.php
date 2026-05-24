<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $reference
 * @property string|null $family
 * @property \Illuminate\Support\Carbon $created_at
 */
final class HdSeedModel extends Model
{
    protected $table = 'hd_seeds';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'reference', 'family', 'created_at'];

    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];

    /**
     * @return HasMany<AddressModel, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(AddressModel::class, 'hd_seed_id');
    }
}
