<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Exception;

use RuntimeException;
use Throwable;

final class SigningClientException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function transport(string $reason, ?Throwable $previous = null): self
    {
        return new self("Signing service transport failure: {$reason}", previous: $previous);
    }

    public static function http(int $status, string $code, string $message): self
    {
        return new self(
            "Signing service rejected request ({$status} {$code}): {$message}",
            httpStatus: $status,
            errorCode: $code,
        );
    }
}
