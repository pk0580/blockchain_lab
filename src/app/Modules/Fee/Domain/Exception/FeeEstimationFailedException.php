<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use RuntimeException;
use Throwable;

final class FeeEstimationFailedException extends RuntimeException
{
    public static function rpc(ChainId $chainId, string $message, ?Throwable $previous = null): self
    {
        return new self(
            "Fee estimation failed for chain '{$chainId->value}': {$message}",
            previous: $previous,
        );
    }

    public static function unusableResponse(ChainId $chainId, string $reason): self
    {
        return new self(
            "Fee estimation for chain '{$chainId->value}' produced unusable response: {$reason}"
        );
    }
}
