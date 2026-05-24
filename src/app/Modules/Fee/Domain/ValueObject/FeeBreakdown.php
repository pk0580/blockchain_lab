<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Sealed-style контракт: ровно две реализации — {@see BitcoinFeeBreakdown} и
 * {@see EvmFeeBreakdown}. PHP не умеет sealed-классы, но через FeeBreakdown
 * как интерфейс мы получаем тип-безопасный union, который потребитель
 * (TxBuilder в Phase 6.2) сможет дискриминировать через `instanceof`.
 *
 * Tron получит свою реализацию, когда мы доберёмся до bandwidth/energy
 * (Phase 6.5+); пока для Tron используется {@see StubFeeEstimator} с
 * BitcoinFeeBreakdown-плейсхолдером, который никогда не дойдёт до broadcast.
 */
interface FeeBreakdown
{
    public function family(): ChainFamily;

    /**
     * Сериализация в JSON-совместимый массив для хранения в `withdrawals.fee_breakdown_json`.
     *
     * @return array<string, scalar>
     */
    public function toArray(): array;
}
