<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Adapter;

use App\Modules\Ledger\Domain\Contract\WalletOwnership;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\AddressLookupModel;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

final readonly class EloquentWalletOwnership implements WalletOwnership
{
    public function findWalletByAddress(ChainFamily $family, string $address): ?WalletId
    {
        $walletId = AddressLookupModel::query()
            ->where('family', $family->value)
            ->where('address', $address)
            ->value('wallet_id');

        return is_string($walletId) && $walletId !== ''
            ? new WalletId($walletId)
            : null;
    }
}
