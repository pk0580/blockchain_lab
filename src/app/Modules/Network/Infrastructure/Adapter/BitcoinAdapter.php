<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Adapter;

use App\Modules\BlockIngestion\Domain\Contract\BlockSource;
use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Contract\AddressValidator;
use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\FeeEstimator;
use App\Modules\Network\Domain\Contract\FinalityPolicy;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\BroadcastFailedException;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Network\Infrastructure\Registry\ConfirmationBasedFinality;
use Throwable;

/**
 * Live BitcoinAdapter — реализация {@see ChainAdapter} для семейства Bitcoin.
 *
 * Обязанности адаптера семейства (GUIDE.md, Урок 4):
 *  - currentHead() — текущая вершина цепи (для сканера и подтверждений);
 *  - broadcast()  — `sendrawtransaction` в bitcoind;
 *  - addressValidator() — валидация адресов через signing-svc;
 *  - feeEstimator() — здесь stub; реальный оценщик — {@see \App\Modules\Fee}.
 *
 * Head/health делегируется в {@see BlockSource} (один BitcoinCoreBlockSource на Chain),
 * валидация адресов — в signing-svc (`/v1/addresses/validate`).
 *
 * @see \GUIDE.md  Урок 4 (#урок-4--l1-l2-и-семейства-сетей)
 */
final readonly class BitcoinAdapter implements ChainAdapter, AddressValidator, FeeEstimator
{
    public function __construct(
        private Chain $chain,
        private BlockSource $blockSource,
        private SigningClient $signing,
        private ?BitcoinRpcClient $rpc = null,
    ) {}

    public function chain(): Chain
    {
        return $this->chain;
    }

    public function family(): ChainFamily
    {
        return ChainFamily::Bitcoin;
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
        return $this->blockSource->currentHead();
    }

    public function isHealthy(): bool
    {
        try {
            $this->blockSource->currentHead();
            return true;
        } catch (BlockSourceException) {
            return false;
        }
    }

    public function supports(TxHash $txHash): bool
    {
        $value = $txHash->value;
        if (str_starts_with($value, '0x')) {
            return false;
        }
        return preg_match('/^[0-9a-fA-F]{64}$/', $value) === 1;
    }

    public function isValid(Address $address): bool
    {
        return $this->signing->isAddressValid(ChainFamily::Bitcoin, (string) $address);
    }

    public function broadcast(SignedRawTx $tx): TxHash
    {
        if ($tx->family !== ChainFamily::Bitcoin) {
            throw BroadcastFailedException::unexpected(
                $this->chain->id,
                "BitcoinAdapter received {$tx->family->value} transaction.",
            );
        }
        if ($this->rpc === null) {
            throw BroadcastFailedException::unexpected(
                $this->chain->id,
                'BitcoinAdapter built without an RPC client — broadcast unavailable.',
            );
        }

        try {
            $result = $this->rpc->call('sendrawtransaction', [$tx->withoutPrefix()]);
        } catch (BlockSourceException $e) {
            throw BroadcastFailedException::transport($this->chain->id, $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw BroadcastFailedException::transport($this->chain->id, $e->getMessage(), $e);
        }

        if (! is_string($result) || $result === '') {
            throw BroadcastFailedException::unexpected(
                $this->chain->id,
                'sendrawtransaction returned empty payload.',
            );
        }

        return new TxHash($result);
    }
}
