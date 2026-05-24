<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal;

use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;

/**
 * Резолвенная горячая кошельковая запись для конкретной сети: адрес, ссылка
 * на HD seed в signing-сервисе и BIP-32 путь. Используется одновременно
 * TxBuilder'ом (откуда списываем UTXO/nonce) и SigningClient'ом (по seed+path).
 */
final readonly class HotWalletDescriptor
{
    public function __construct(
        public HotAddress $address,
        public string $seedReference,
        public string $derivationPath,
    ) {}
}
