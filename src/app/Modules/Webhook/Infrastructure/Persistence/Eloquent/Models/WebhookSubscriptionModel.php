<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $url
 * @property string $secret
 * @property array<int, string>|string $events
 * @property bool $active
 * @property \Illuminate\Support\Carbon $created_at
 */
final class WebhookSubscriptionModel extends Model
{
    protected $table = 'webhook_subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'url', 'secret', 'events', 'active', 'created_at'];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'created_at' => 'immutable_datetime',
    ];
}
