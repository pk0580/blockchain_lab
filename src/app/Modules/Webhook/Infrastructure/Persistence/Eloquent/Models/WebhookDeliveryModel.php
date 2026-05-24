<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $outbox_id
 * @property string $subscription_id
 * @property string $event_name
 * @property array<string, mixed>|string $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property int|null $last_response_status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 */
final class WebhookDeliveryModel extends Model
{
    protected $table = 'webhook_deliveries';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'outbox_id', 'subscription_id', 'event_name', 'payload',
        'status', 'attempts', 'last_error', 'last_response_status',
        'created_at', 'scheduled_at', 'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'last_response_status' => 'integer',
        'created_at' => 'immutable_datetime',
        'scheduled_at' => 'immutable_datetime',
        'delivered_at' => 'immutable_datetime',
    ];
}
