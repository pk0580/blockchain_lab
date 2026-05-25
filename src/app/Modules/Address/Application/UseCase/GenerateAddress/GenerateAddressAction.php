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

/**
 * Выводит новый адрес из HD-сида и записывает его в БД.
 *
 * Полный алгоритм — GUIDE.md, Урок 2, раздел «Создание адреса».
 *
 * ⚠️ Шаг с advisory-lock критичен: без сериализации выделения индекса два
 * параллельных запроса могли бы получить одинаковый индекс → одинаковый
 * адрес → нарушение уникальности. Тот же приём в [[EloquentNonceAllocator]]
 * для EVM-nonce (GUIDE.md, Урок 10).
 *
 * @see \GUIDE.md  Урок 2 (#урок-2--ключи-адреса-и-hd-кошельки)
 */
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

        // Шаг 1 (GUIDE §2): загрузить сид. Если у сида прибито family — проверить совпадение.
        $seed = $this->seeds->findById($seedId)
            ?? throw HdSeedNotFoundException::byId($seedId);

        if (! $seed->supportsFamily($family)) {
            throw new DomainException(
                "HdSeed '{$seedId->value}' does not support family '{$family->value}'."
            );
        }

        // Выделение индекса + вызов signing-svc + вставка в БД в одной транзакции.
        // Метод nextDerivationIndex репозитория ставит advisory-lock на пару (seed, family) —
        // см. GUIDE.md, Урок 2: без этого два параллельных запроса получили бы одинаковый
        // индекс. Тот же подход применён для nonce — см. GUIDE.md, Урок 10.
        return $this->db->transaction(function () use ($seed, $seedId, $family, $walletId): AddressDescriptor {
            // Шаг 2 (GUIDE §2): выделить следующий индекс под advisory-lock.
            $index = $this->addresses->nextDerivationIndex($seedId, $family);

            // Шаг 3 (GUIDE §2): собрать путь BIP-44 для семейства.
            $path = $this->paths->buildExternal($family, $index);

            // Шаг 4 (GUIDE §2): попросить signing-svc вывести адрес.
            // Приватный ключ остаётся внутри signing-svc; нам приходит только публичный адрес.
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

            // Шаг 5 (GUIDE §2): сохранить запись + поднять событие AddressGenerated после COMMIT.
            // На это событие реагирует [[RegisterAddressInDirectory]] — добавляет адрес
            // в Redis-набор, чтобы сканер мог в O(1) проверить «наш ли этот выход?»
            // (см. GUIDE.md, Урок 5, раздел «Почему Redis, а не PostgreSQL»).
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
