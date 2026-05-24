<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Unsigned, fully-serialized transaction в hex (или PSBT для BTC) + опциональные
 * "extra"-метаданные для signing-svc: для BTC — список prev-outs, для EVM — nonce и chain_id.
 *
 * Структура `extras` зависит от family и проверяется конкретным `TxBuilder`'ом.
 * Передаётся в `SigningClient::signRawTx`.
 */
final readonly class BuiltTransaction
{
    /**
     * @param array<string, mixed> $signingExtras
     */
    public function __construct(
        public string $rawHex,
        public array $signingExtras = [],
    ) {
        if ($rawHex === '') {
            throw new InvalidArgumentException('BuiltTransaction.rawHex must be non-empty.');
        }
    }
}
