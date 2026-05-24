<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only проекция таблицы `ledger_entries` для dashboard'а Education.
 * Не дублирует {@see \App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\LedgerEntryModel},
 * а намеренно держит свой узкий касет: dashboard не должен опираться на
 * Infrastructure чужого модуля.
 *
 * @property string $id
 * @property string $wallet_id
 * @property string $chain_id
 * @property string $direction
 * @property string $amount
 * @property string $currency
 * @property string $operation_type
 * @property string $status
 * @property string|null $related_tx_hash
 * @property \Illuminate\Support\Carbon $created_at
 */
final class LedgerEntryLookupModel extends Model
{
    protected $table = 'ledger_entries';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];
}
