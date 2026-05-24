<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use RuntimeException;
use Throwable;

final class NonceAllocationFailedException extends RuntimeException
{
    public static function rpc(ChainId $chainId, HotAddress $hot, string $message, ?Throwable $previous = null): self
    {
        return new self(
            "Nonce allocation for {$chainId->value}/{$hot->value} failed: {$message}",
            previous: $previous,
        );
    }

    public static function collision(ChainId $chainId, HotAddress $hot, int $nonce): self
    {
        return new self(
            "Nonce {$nonce} for {$chainId->value}/{$hot->value} was inserted by another process (race)."
        );
    }
}
