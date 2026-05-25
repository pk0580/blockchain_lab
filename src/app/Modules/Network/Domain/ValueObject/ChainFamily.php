<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use App\Modules\Network\Domain\Exception\UnknownChainFamilyException;

/**
 * Семейство блокчейн-сети — «контракт работы с сетью».
 *
 * Идея, описанная в GUIDE.md, Урок 4 «L1, L2 и семейства сетей»: одно семейство
 * описывает класс сетей, ведущих себя одинаково (одна модель транзакций, тот же
 * формат адреса, тот же RPC). Конкретная сеть (chain) — это инстанция семейства:
 *
 *   Bitcoin family → mainnet, testnet, regtest, signet, …
 *   EVM family     → Ethereum, Polygon, Arbitrum, Optimism, Base, …
 *   Tron family    → mainnet, Nile, …
 *
 * Добавить новую EVM-L2 = записать строчку в БД через {@see RegisterChainAction}.
 * Добавить новое семейство = новый ChainAdapter + новые контракты в реестре.
 *
 * @see \GUIDE.md  Урок 4 (#урок-4--l1-l2-и-семейства-сетей)
 */
enum ChainFamily: string
{
    case Bitcoin = 'bitcoin';
    case Evm = 'evm';
    case Tron = 'tron';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new UnknownChainFamilyException($value);
    }
}
