<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $request_hash
 * @property string $method
 * @property string $path
 * @property int $response_status
 * @property string $response_body
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $expires_at
 */
final class IdempotencyKeyModel extends Model
{
    protected $table = 'idempotency_keys';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'response_status' => 'integer',
        'created_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
    ];
}
