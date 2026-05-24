<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\UseCase\GenerateAddress;

use App\Modules\Address\Application\ReadModel\AddressDescriptor;
use App\Modules\Address\Domain\Entity\Address;
use App\Modules\Address\Domain\Exception\HdSeedNotFoundException;
use App\Modules\Address\Domain\Repository\AddressRepository;
use App\Modules\Address\Domain\Repository\HdSeedRepository;
use App\Modules\Address\Domain\Service\DerivationPathFactory;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

final readonly class GenerateAddressAction
{
    public function __construct(
        private HdSeedRepository $seeds,
        private AddressRepository $addresses,
        private DerivationPathFactory $paths,
        private SigningClient $signing,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(GenerateAddressData $data): AddressDescriptor
    {
        $seedId = new HdSeedId($data->seedId);
        $family = ChainFamily::fromString($data->family);
        $walletId = $data->walletId !== null ? new WalletId($data->walletId) : null;

        $seed = $this->seeds->findById($seedId)
            ?? throw HdSeedNotFoundException::byId($seedId);

        if (! $seed->supportsFamily($family)) {
            throw new DomainException(
                "HdSeed '{$seedId->value}' does not support family '{$family->value}'."
            );
        }

        // Выделение индекса + вызов signing-svc + вставка в БД в одной транзакции.
        // Метод nextDerivationIndex репозитория устанавливает рекомендательную блокировку (advisory lock),
        // чтобы два одновременных запроса для одного и того же (seed, family) не могли
        // получить один и тот же индекс.
        return $this->db->transaction(function () use ($seed, $seedId, $family, $walletId): AddressDescriptor {
            $index = $this->addresses->nextDerivationIndex($seedId, $family);
            $path = $this->paths->buildExternal($family, $index);

            $derived = $this->signing->deriveAddress(
                seedReference: (string) $seed->reference,
                family: $family,
                path: (string) $path,
            );

            $address = Address::generate(
                id: new AddressId((string) Str::uuid()),
                seedId: $seedId,
                family: $family,
                address: $derived,
                derivationPath: $path,
                walletId: $walletId,
                now: new DateTimeImmutable(),
            );

            $this->addresses->save($address);

            $events = $address->pullPendingEvents();
            $this->db->afterCommit(function () use ($events): void {
                foreach ($events as $event) {
                    $this->events->dispatch($event);
                }
            });

            return new AddressDescriptor(
                id: $address->id->value,
                seedId: $seed->id->value,
                family: $family->value,
                address: (string) $derived,
                derivationPath: (string) $path,
                walletId: $walletId?->value,
            );
        });
    }
}
