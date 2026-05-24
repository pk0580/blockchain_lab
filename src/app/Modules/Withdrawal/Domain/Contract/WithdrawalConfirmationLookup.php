<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Exception\ConfirmationLookupFailedException;
use App\Modules\Withdrawal\Domain\ValueObject\ConfirmationObservation;

/**
 * Узкий read-only port: «сколько подтверждений у этой транзакции прямо сейчас».
 * Реализуется per-family в Infrastructure (Bitcoin Core `getrawtransaction`,
 * EVM `eth_getTransactionByHash`+`eth_blockNumber`). Тонкая обёртка вокруг RPC,
 * чтобы Application::UpdateWithdrawalConfirmationsAction не знал ни про
 * Bitcoind, ни про JSON-RPC.
 *
 * Контракт идемпотентен — два последовательных вызова с тем же tx_hash возвращают
 * одинаковую наблюдённую величину (с поправкой на реальный прогресс цепи).
 */
interface WithdrawalConfirmationLookup
{
    /**
     * @throws ConfirmationLookupFailedException транспортный / RPC-сбой; jobs ловят это
     *         и пропускают конкретную запись, не падая на всю партию.
     */
    public function observe(Chain $chain, TxHash $txHash): ConfirmationObservation;
}
