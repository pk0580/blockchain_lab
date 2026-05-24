<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $event_name
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $published_at
 */
final class OutboxMessageLookupModel extends Model
{
    protected $table = 'outbox_messages';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $casts = [
        'created_at' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
    ];
}
