<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Собственное view ReorgDetection поверх общей таблицы `blocks`. Используется
 * для read-port'а ChainHistory и delete-операций при reorg. Никаких ссылок
 * на BlockIngestion::Infrastructure не делает.
 *
 * @property string $chain_id
 * @property int $height
 * @property string $hash
 * @property string $parent_hash
 */
final class BlockReadModel extends Model
{
    protected $table = 'blocks';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'height';

    protected $keyType = 'int';

    protected $casts = [
        'height' => 'integer',
    ];
}
