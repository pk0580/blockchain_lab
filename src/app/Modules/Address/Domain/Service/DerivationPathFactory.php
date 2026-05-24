<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Service;

use App\Modules\Address\Domain\ValueObject\DerivationIndex;
use App\Modules\Address\Domain\ValueObject\DerivationPath;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Создает пути деривации по стандарту BIP-44 для конкретных семейств блокчейнов.
 *
 * Типы монет (SLIP-44):
 *   - Bitcoin = 0  (мы используем BIP-84 P2WPKH, purpose 84)
 *   - Ethereum = 60 (Polygon и другие EVM-сети используют его в нашей конфигурации)
 *   - Tron = 195
 *
 * Аккаунт зафиксирован на 0 в Фазе 3. Деривация по нескольким аккаунтам
 * относится к Фазе 6 (горячие кошельки для каждого мерчанта).
 */
final class DerivationPathFactory
{
    private const int ACCOUNT = 0;
    private const int CHANGE = 0;

    public function buildExternal(ChainFamily $family, DerivationIndex $index): DerivationPath
    {
        $i = $index->value;
        $a = self::ACCOUNT;
        $c = self::CHANGE;

        return new DerivationPath(match ($family) {
            ChainFamily::Bitcoin => "m/84'/0'/{$a}'/{$c}/{$i}",
            ChainFamily::Evm => "m/44'/60'/{$a}'/{$c}/{$i}",
            ChainFamily::Tron => "m/44'/195'/{$a}'/{$c}/{$i}",
        });
    }
}
