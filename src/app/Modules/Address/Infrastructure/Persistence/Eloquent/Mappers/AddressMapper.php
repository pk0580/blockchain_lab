<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Address\Domain\Entity\Address;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\DerivationPath;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\WalletId;
use App\Modules\Address\Infrastructure\Persistence\Eloquent\Models\AddressModel;
use App\Modules\Network\Domain\ValueObject\Address as AddressVO;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

final class AddressMapper
{
    public function toDomain(AddressModel $row): Address
    {
        return Address::reconstitute(
            id: new AddressId($row->id),
            seedId: new HdSeedId($row->hd_seed_id),
            family: ChainFamily::from($row->family),
            address: new AddressVO($row->address),
            derivationPath: new DerivationPath($row->derivation_path),
            walletId: $row->wallet_id !== null ? new WalletId($row->wallet_id) : null,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
        );
    }

    /**
     * @return array{
     *     id: string, hd_seed_id: string, family: string, address: string,
     *     derivation_path: string, derivation_index: int, wallet_id: string|null,
     *     created_at: \DateTimeImmutable
     * }
     */
    public function toRow(Address $address): array
    {
        return [
            'id' => $address->id->value,
            'hd_seed_id' => $address->seedId->value,
            'family' => $address->family->value,
            'address' => (string) $address->address,
            'derivation_path' => (string) $address->derivationPath,
            'derivation_index' => $address->derivationPath->leafIndex()->value,
            'wallet_id' => $address->walletId()?->value,
            'created_at' => $address->createdAt,
        ];
    }
}
