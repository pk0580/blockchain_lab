<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use RuntimeException;
use Throwable;

final class BroadcastFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $rpcCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function transport(ChainId $chainId, string $reason, ?Throwable $previous = null): self
    {
        return new self("Broadcast for {$chainId->value} failed (transport): {$reason}", previous: $previous);
    }

    public static function rpc(ChainId $chainId, string $code, string $message): self
    {
        return new self("Broadcast for {$chainId->value} rejected by node ({$code}): {$message}", rpcCode: $code);
    }

    public static function unexpected(ChainId $chainId, string $reason): self
    {
        return new self("Broadcast for {$chainId->value} unusable response: {$reason}");
    }
}
