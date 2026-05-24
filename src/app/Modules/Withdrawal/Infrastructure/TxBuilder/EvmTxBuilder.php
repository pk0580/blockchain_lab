<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\TxBuilder;

use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use App\Modules\Withdrawal\Domain\Contract\TxBuilder;
use App\Modules\Withdrawal\Domain\ValueObject\BuiltTransaction;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use RuntimeException;

/**
 * Готовит неподписанную (unsigned) EIP-1559 (тип 2) транзакцию. RLP-сериализация и подпись
 * выполняются в Go signing-svc — здесь мы лишь упаковываем поля в hex и
 * передаём всё в "signing extras", откуда `HttpSigningClient::signRawTx`
 * их подхватит. То есть `rawHex` в Фазе 6.2 является детерминированным
 * плейсхолдером (`tx-pending-{chainId}-{nonce}`); реальный RLP формируется
 * подписантом на основе `signingExtras`. Когда мы захотим подписывать локально или
 * валидировать на стороне Laravel — мы заменим плейсхолдер на полноценную RLP-сборку.
 *
 * Такой выбор соответствует принципу «приватные ключи никогда не покидают
 * signing-svc»: PHP знает поля транзакции, но не выполняет криптографию.
 */
final readonly class EvmTxBuilder implements TxBuilder
{
    public function __construct(
        private EvmJsonRpc $rpc,
        private RpcEndpointPicker $endpoints,
    ) {}

    public function build(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $nonce,
    ): BuiltTransaction {
        if ($chain->family !== ChainFamily::Evm) {
            throw new RuntimeException(
                "EvmTxBuilder не может строить транзакции для семейства '{$chain->family->value}'."
            );
        }
        if ($nonce === null) {
            throw new RuntimeException('EvmTxBuilder требует предварительно выделенный nonce.');
        }

        $maxFee = (string) ($fee->breakdown['max_fee_per_gas_wei'] ?? '');
        $maxPriority = (string) ($fee->breakdown['max_priority_fee_per_gas_wei'] ?? '');
        $gasLimit = (int) ($fee->breakdown['gas_limit'] ?? 0);
        if ($maxFee === '' || $maxPriority === '' || $gasLimit < 21000) {
            throw new RuntimeException(
                'EvmTxBuilder требует наличия max_fee_per_gas_wei, max_priority_fee_per_gas_wei, gas_limit в снимке комиссии.'
            );
        }

        $chainIdInt = $this->rpc->chainId($this->httpUrl($chain));

        $extras = [
            'chain_id' => $chainIdInt,
            'nonce' => $nonce->value,
            'max_fee_per_gas_wei' => $maxFee,
            'max_priority_fee_per_gas_wei' => $maxPriority,
            'gas_limit' => $gasLimit,
            'to' => $to->value,
            'value_wei' => $amount->value,
        ];

        // Плейсхолдер rawHex: уникальный для пары (chain, nonce). Реальную RLP-сборку
        // делает signing-svc на основе extras. Префикс `tx-` отличает его от
        // подписанной транзакции (она начинается с `0x...`).
        $rawHex = sprintf('tx-pending-%s-%d', $chain->id->value, $nonce->value);

        return new BuiltTransaction(rawHex: $rawHex, signingExtras: $extras);
    }

    public function rebuild(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $previousNonce,
        ?array $previousExtras,
    ): BuiltTransaction {
        if ($chain->family !== ChainFamily::Evm) {
            throw new RuntimeException(
                "EvmTxBuilder не может пересобирать транзакции для семейства '{$chain->family->value}'."
            );
        }
        if ($previousNonce === null) {
            throw new RuntimeException('EvmTxBuilder::rebuild требует оригинальный nonce.');
        }
        // Замена в EVM = тот же nonce + повышенный газ. Это не "RBF" в
        // строгом смысле, но любой EVM-узел заменит ожидающую (pending) транзакцию с тем же
        // (from, nonce) при достаточной разнице в цене газа (обычно >=10%-12.5%).
        return $this->build($chain, $from, $to, $amount, $fee, $previousNonce);
    }

    private function httpUrl(Chain $chain): string
    {
        return $this->endpoints->pick($chain)->url;
    }
}
