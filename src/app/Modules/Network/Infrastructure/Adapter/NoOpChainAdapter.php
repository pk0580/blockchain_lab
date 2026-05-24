<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Adapter;

use App\Modules\Network\Domain\Contract\AddressValidator;
use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\FeeEstimator;
use App\Modules\Network\Domain\Contract\FinalityPolicy;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\BroadcastFailedException;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Network\Infrastructure\Registry\ConfirmationBasedFinality;

/**
 * Test double. Behaves like a healthy adapter pinned at a fixed head with
 * permissive address validation. Concrete adapters (BitcoinAdapter,
 * EvmAdapter, TronAdapter) land in Phase 4.
 */
final readonly class NoOpChainAdapter implements ChainAdapter, AddressValidator, FeeEstimator
{
    public function __construct(
        private Chain $chain,
        private int $headHeight = 0,
        private bool $healthy = true,
    ) {}

    public function chain(): Chain
    {
        return $this->chain;
    }

    public function family(): ChainFamily
    {
        return $this->chain->family;
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
        return new BlockHeight($this->headHeight);
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function supports(TxHash $txHash): bool
    {
        return true;
    }

    public function isValid(Address $address): bool
    {
        return true;
    }

    public function broadcast(SignedRawTx $tx): TxHash
    {
        throw BroadcastFailedException::unexpected(
            $this->chain->id,
            'NoOpChainAdapter cannot broadcast — use a live adapter.',
        );
    }
}
