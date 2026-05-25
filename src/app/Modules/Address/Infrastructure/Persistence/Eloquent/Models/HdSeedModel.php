<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-модель таблицы `hd_seeds` — «учётная карточка» сида, который
 * физически хранится в зашифрованном файле внутри signing-svc.
 *
 * Минимальный набор колонок описан в GUIDE.md, Урок 2, раздел «Ссылка на ключ».
 * В этой таблице НЕТ ни мнемоники, ни мастер-ключа, ни одного байта секретного
 * материала — только метаданные. Аналогия из GUIDE: запись в блокноте про
 * мешок с ключами, который лежит в банковском сейфе.
 *
 * @property string $id
 * @property string $reference   Непрозрачная ссылка; signing-svc находит по ней `<reference>.sealed`
 * @property string|null $family bitcoin|evm|tron, либо NULL = универсальный сид
 * @property \Illuminate\Support\Carbon $created_at
 * @see \GUIDE.md  Урок 2 (#урок-2--ключи-адреса-и-hd-кошельки)
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
