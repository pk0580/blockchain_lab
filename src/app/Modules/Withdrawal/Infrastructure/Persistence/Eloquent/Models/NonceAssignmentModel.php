<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $chain_id
 * @property string $hot_address
 * @property int $nonce
 * @property string|null $withdrawal_id
 * @property \Illuminate\Support\Carbon $allocated_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
final class NonceAssignmentModel extends Model
{
    protected $table = 'nonce_assignments';

    public $incrementing = false;

    public $timestamps = false;

    // Композитный PK (chain_id, hot_address, nonce). Eloquent не поддерживает
    // составные ключи на уровне $primaryKey, поэтому номинально указываем
    // одно из полей. Все операции делаем через query()->where() / ->insert(),
    // не вызываем save()/find() — иначе Eloquent попробует трактовать
    // chain_id как уникальный.
    protected $primaryKey = 'chain_id';

    protected $fillable = [
        'chain_id', 'hot_address', 'nonce',
        'withdrawal_id', 'allocated_at', 'used_at',
    ];

    protected $casts = [
        'nonce' => 'integer',
        'allocated_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
    ];
}
