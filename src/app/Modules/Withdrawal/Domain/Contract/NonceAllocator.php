<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;

/**
 * Аллоцирует следующий nonce для (chain, hot_address). Должен быть безопасен
 * при конкурентных вызовах с нескольких worker'ов — реализация по умолчанию
 * полагается на PG advisory_xact_lock + UNIQUE (chain_id, hot_address, nonce).
 *
 * Для семейств без nonce-модели (Bitcoin) реализация может либо бросать
 * исключение, либо возвращать NonceValue(0) — Phase 6.2 будет вызывать
 * allocate только для EVM. Контракт не пытается это запретить: гибче.
 */
interface NonceAllocator
{
    /**
     * @throws \App\Modules\Withdrawal\Domain\Exception\NonceAllocationFailedException
     */
    public function allocate(Chain $chain, HotAddress $hot): NonceValue;
}
