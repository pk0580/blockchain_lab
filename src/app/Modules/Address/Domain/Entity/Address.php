<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Entity;

use App\Modules\Address\Domain\Event\AddressGenerated;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\DerivationPath;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\Address as AddressVO;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

/**
 * Корень агрегата для одного производного адреса. Жизненный цикл не зависит
 * от HdSeed (у сида может быть много адресов; адрес может пережить свой сид,
 * если тот будет архивирован). Связь с кошельком может быть nullable в Фазе 3;
 * в Фазе 5 адреса будут привязываться к кошелькам через отдельный вариант
 * использования AssignAddressToWallet.
 */
final class Address
{
    /** @var list<object> */
    private array $pendingEvents = [];

    private function __construct(
        public readonly AddressId $id,
        public readonly HdSeedId $seedId,
        public readonly ChainFamily $family,
        public readonly AddressVO $address,
        public readonly DerivationPath $derivationPath,
        private ?WalletId $walletId,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function generate(
        AddressId $id,
        HdSeedId $seedId,
        ChainFamily $family,
        AddressVO $address,
        DerivationPath $derivationPath,
        ?WalletId $walletId,
        DateTimeImmutable $now,
    ): self {
        $entity = new self($id, $seedId, $family, $address, $derivationPath, $walletId, $now);
        $entity->pendingEvents[] = new AddressGenerated(
            addressId: $id,
            seedId: $seedId,
            family: $family,
            address: (string) $address,
            derivationPath: (string) $derivationPath,
            occurredAt: $now,
        );
        return $entity;
    }

    public static function reconstitute(
        AddressId $id,
        HdSeedId $seedId,
        ChainFamily $family,
        AddressVO $address,
        DerivationPath $derivationPath,
        ?WalletId $walletId,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $seedId, $family, $address, $derivationPath, $walletId, $createdAt);
    }

    public function walletId(): ?WalletId
    {
        return $this->walletId;
    }

    /**
     * @return list<object>
     */
    public function pullPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }
}
