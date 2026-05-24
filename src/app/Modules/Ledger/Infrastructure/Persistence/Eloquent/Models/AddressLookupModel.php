<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view над `addresses` (таблицей владеет Address модуль). Ledger
 * читает только `family`, `address`, `wallet_id` для резолва owner'а.
 *
 * @property string $family
 * @property string $address
 * @property string|null $wallet_id
 */
final class AddressLookupModel extends Model
{
    protected $table = 'addresses';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';
}
