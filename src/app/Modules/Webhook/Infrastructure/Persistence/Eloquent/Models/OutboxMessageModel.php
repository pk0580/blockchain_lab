<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $event_name
 * @property string $aggregate_id
 * @property array<string, mixed>|string $payload
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $attempts
 * @property string|null $last_error
 */
final class OutboxMessageModel extends Model
{
    protected $table = 'outbox_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'event_name', 'aggregate_id', 'payload',
        'created_at', 'published_at', 'attempts', 'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'created_at' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
    ];
}
