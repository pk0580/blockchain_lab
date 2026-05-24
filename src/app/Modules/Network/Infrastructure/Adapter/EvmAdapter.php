<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Adapter;

use App\Modules\Network\Domain\Contract\AddressValidator;
use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\FeeEstimator;
use App\Modules\Network\Domain\Contract\FinalityPolicy;
use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\BroadcastFailedException;
use App\Modules\Network\Domain\Exception\NoRpcEndpointException;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Network\Infrastructure\Registry\ConfirmationBasedFinality;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use Throwable;

/**
 * Live EVM adapter — обслуживает Sepolia, Polygon Amoy, любую EVM-совместимую
 * сеть. Все семейство-зависимые операции (currentHead, broadcast) ходят в
 * {@see EvmJsonRpc}; fee estimation остаётся за {@see \App\Modules\Fee} модулем,
 * здесь — лишь stub-метод feeEstimator(), который возвращает себя (методы
 * estimate() не вызываются, потому что Phase 6.2 не пользуется ChainAdapter::feeEstimator).
 */
final readonly class EvmAdapter implements ChainAdapter, AddressValidator, FeeEstimator
{
    public function __construct(
        private Chain $chain,
        private EvmJsonRpc $rpc,
        private SigningClient $signing,
        private RpcEndpointPicker $endpoints,
    ) {}

    public function chain(): Chain
    {
        return $this->chain;
    }

    public function family(): ChainFamily
    {
        return ChainFamily::Evm;
    }

    public function finality(): FinalityPolicy
    {
        return new ConfirmationBasedFinality($this->chain->confirmationRequirement);
    }

    public function addressValidator(): AddressValidator
    {
        return $this;
    }

    public function feeEstimator(): FeeEstimator
    {
        return $this;
    }

    public function currentHead(): BlockHeight
    {
        try {
            $height = $this->rpc->blockNumber($this->httpUrl());
        } catch (Throwable $e) {
            throw BroadcastFailedException::transport($this->chain->id, $e->getMessage(), $e);
        }

        return new BlockHeight($height);
    }

    public function isHealthy(): bool
    {
        try {
            $this->rpc->blockNumber($this->httpUrl());
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function supports(TxHash $txHash): bool
    {
        $value = strtolower($txHash->value);
        if (! str_starts_with($value, '0x')) {
            return false;
        }
        return preg_match('/^0x[0-9a-f]{64}$/', $value) === 1;
    }

    public function isValid(Address $address): bool
    {
        return $this->signing->isAddressValid(ChainFamily::Evm, (string) $address);
    }

    public function broadcast(SignedRawTx $tx): TxHash
    {
        if ($tx->family !== ChainFamily::Evm) {
            throw BroadcastFailedException::unexpected(
                $this->chain->id,
                "EvmAdapter received {$tx->family->value} transaction.",
            );
        }

        $hash = $this->rpc->sendRawTransaction($this->chain->id, $this->httpUrl(), $tx->withPrefix());

        return new TxHash($hash);
    }

    private function httpUrl(): string
    {
        try {
            return $this->endpoints->pick($this->chain)->url;
        } catch (NoRpcEndpointException $e) {
            throw BroadcastFailedException::unexpected($this->chain->id, $e->getMessage());
        }
    }
}
