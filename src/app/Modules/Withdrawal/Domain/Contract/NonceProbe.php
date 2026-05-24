<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;

/**
 * Источник «правды» о следующем nonce из самой сети (EVM:
 * `eth_getTransactionCount(addr, 'pending')`). Вызывается только когда у нас
 * нет своих записей в `nonce_assignments` — иначе наш счётчик авторитетнее.
 *
 * Возвращает null, если probe для этого семейства не имеет смысла (Bitcoin)
 * или RPC недоступен — allocator должен решать политику fallback самостоятельно.
 */
interface NonceProbe
{
    public function probe(Chain $chain, HotAddress $hot): ?NonceValue;
}
