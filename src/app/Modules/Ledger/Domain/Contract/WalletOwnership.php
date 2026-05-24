<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Contract;

use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Port для резолва владельца адреса. Реализация в Ledger::Infrastructure
 * читает таблицу `addresses` (которой владеет Address модуль), но Ledger::Domain
 * остаётся независимым от Address::Domain.
 */
interface WalletOwnership
{
    public function findWalletByAddress(ChainFamily $family, string $address): ?WalletId;
}
