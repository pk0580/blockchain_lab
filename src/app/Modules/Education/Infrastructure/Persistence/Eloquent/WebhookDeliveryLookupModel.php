<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $status
 */
final class WebhookDeliveryLookupModel extends Model
{
    protected $table = 'webhook_deliveries';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';
}
