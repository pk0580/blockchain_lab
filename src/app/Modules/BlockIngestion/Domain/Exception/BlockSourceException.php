<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Raised by BlockSource adapters when the upstream node refuses to answer
 * or returns an unparseable payload. Application layer catches this and
 * leaves the cursor untouched; the next scan tick re-tries the same height.
 */
final class BlockSourceException extends RuntimeException
{
    public static function transport(string $message, ?Throwable $previous = null): self
    {
        return new self("Block source transport error: {$message}", previous: $previous);
    }

    public static function protocol(string $message): self
    {
        return new self("Block source protocol error: {$message}");
    }

    public static function notFound(string $what): self
    {
        return new self("Block source missing data: {$what}");
    }
}
