<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Withdrawal\Domain\ValueObject\BuiltTransaction;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;

/**
 * Строит unsigned транзакцию под конкретное chain-family. Подпись не выполняет —
 * это делает `SigningClient::signRawTx`.
 *
 * - BTC: подбирает UTXO через listunspent, формирует raw transaction через
 *   createrawtransaction (или PSBT в будущем). Возвращает hex + список prev-outs
 *   в signingExtras для подписи signing-сервисом.
 * - EVM: сериализует EIP-1559 (type 2) транзакцию через RLP. Возвращает hex и
 *   метаданные (chain_id, nonce) — подпись производит Go-сервис.
 */
interface TxBuilder
{
    public function build(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $nonce,
    ): BuiltTransaction;

    /**
     * Пересобирает replacement-транзакцию для stuck withdrawal:
     *
     * - **BTC (BIP-125 RBF):** тот же набор UTXO (передаётся через $previousExtras['inputs']),
     *   sequence < 0xfffffffe, более высокая комиссия. Без переиспользования inputs
     *   это не RBF, а двойная трата.
     * - **EVM:** тот же nonce (из $previousNonce), более высокие maxFeePerGas /
     *   maxPriorityFeePerGas.
     *
     * @param array<string, mixed>|null $previousExtras сборочные метаданные оригинала.
     */
    public function rebuild(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $previousNonce,
        ?array $previousExtras,
    ): BuiltTransaction;
}
