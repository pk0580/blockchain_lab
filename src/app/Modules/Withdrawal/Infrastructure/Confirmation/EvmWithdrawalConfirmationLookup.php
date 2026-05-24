<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Confirmation;

use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use App\Modules\Network\Infrastructure\Rpc\RpcError;
use App\Modules\Network\Infrastructure\Rpc\TransportError;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Domain\Exception\ConfirmationLookupFailedException;
use App\Modules\Withdrawal\Domain\ValueObject\ConfirmationObservation;
use RuntimeException;
use Throwable;

/**
 * EVM lookup: `eth_getTransactionByHash` + `eth_blockNumber`.
 *
 *  - `result === null` (узел не помнит транзакцию) ⇒ {@see ConfirmationObservation::dropped()}.
 *  - `blockNumber === null` (есть, но pending в мемпуле) ⇒ {@see ConfirmationObservation::pending()}.
 *  - иначе ⇒ confirmations = headBlock − txBlock + 1 (минимум 1).
 *
 * Здесь намеренно два RPC-запроса вместо одного `eth_getTransactionReceipt` (где confirmations не возвращается напрямую). Кэширование `eth_blockNumber` между tx'ами в одном тике — задача Phase 7+ (NodeHealth + batch RPC).
 */
final readonly class EvmWithdrawalConfirmationLookup implements WithdrawalConfirmationLookup
{
    public function __construct(
        private EvmJsonRpc $rpc,
        private RpcEndpointPicker $endpoints,
    ) {}

    public function observe(Chain $chain, TxHash $txHash): ConfirmationObservation
    {
        if ($chain->family !== ChainFamily::Evm) {
            throw new RuntimeException(
                "EvmWithdrawalConfirmationLookup cannot serve family '{$chain->family->value}'."
            );
        }

        $url = $this->httpUrl($chain);

        try {
            /** @var array<string, mixed>|null $tx */
            $tx = $this->rpc->call($url, 'eth_getTransactionByHash', [$txHash->value]);
        } catch (RpcError $e) {
            throw ConfirmationLookupFailedException::for($chain->id, $txHash, $e->getMessage(), $e);
        } catch (TransportError $e) {
            throw ConfirmationLookupFailedException::for($chain->id, $txHash, $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw ConfirmationLookupFailedException::for($chain->id, $txHash, $e->getMessage(), $e);
        }

        if ($tx === null) {
            return ConfirmationObservation::dropped();
        }

        $blockNumberHex = $tx['blockNumber'] ?? null;
        if (! is_string($blockNumberHex) || $blockNumberHex === '') {
            return ConfirmationObservation::pending();
        }

        try {
            $headBlock = $this->rpc->blockNumber($url);
        } catch (Throwable $e) {
            throw ConfirmationLookupFailedException::for($chain->id, $txHash, $e->getMessage(), $e);
        }

        $txBlock = $this->hexToInt($blockNumberHex);
        $confirmations = max(1, $headBlock - $txBlock + 1);

        return ConfirmationObservation::confirmed($confirmations);
    }

    private function httpUrl(Chain $chain): string
    {
        return $this->endpoints->pick($chain)->url;
    }

    private function hexToInt(string $hex): int
    {
        $clean = strtolower(trim($hex));
        if ($clean === '' || $clean === '0x' || $clean === '0x0') {
            return 0;
        }
        if (str_starts_with($clean, '0x')) {
            $clean = substr($clean, 2);
        }
        if (preg_match('/^[0-9a-f]+$/', $clean) !== 1) {
            throw new RuntimeException("EVM RPC: non-hex quantity '{$hex}'.");
        }
        $value = hexdec($clean);
        return is_int($value) ? $value : (int) $value;
    }
}
