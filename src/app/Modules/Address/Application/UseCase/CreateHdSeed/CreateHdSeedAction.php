<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\UseCase\CreateHdSeed;

use App\Modules\Address\Domain\Entity\HdSeed;
use App\Modules\Address\Domain\Repository\HdSeedRepository;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\HdSeedReference;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Регистрация HD-сида: создаёт (или импортирует) мнемонику в signing-svc и
 * записывает её «учётную карточку» в таблицу `hd_seeds`.
 *
 * Полный алгоритм и обоснование порядка шагов — см. GUIDE.md, Урок 2,
 * раздел «Что делает CreateHdSeedAction::handle()».
 *
 * ⚠️ Порядок «сначала signing-svc, потом БД» не случаен. Обратный порядок
 * оставил бы карточку без реального сида при падении signing-svc; текущий
 * безопасно ретраится: `ensureSeed` идемпотентен.
 *
 * @see \GUIDE.md  Урок 2 (#урок-2--ключи-адреса-и-hd-кошельки)
 */
final readonly class CreateHdSeedAction
{
    public function __construct(
        private HdSeedRepository $seeds,
        private SigningClient $signing,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(CreateHdSeedData $data): HdSeedId
    {
        // Шаг 1 (GUIDE §2): валидируем `reference` через VO ещё до похода в сеть.
        $reference = new HdSeedReference($data->reference);
        $family = $data->family !== null ? ChainFamily::fromString($data->family) : null;

        // Шаг 2 (GUIDE §2): signing-svc — первоисточник правды о том, существует ли этот
        // сид. POST /v1/seeds идемпотентен: для уже зарегистрированного reference вернёт
        // created=false, новой мнемоники не сгенерирует.
        $this->signing->ensureSeed((string) $reference, $data->importMnemonic);

        $id = new HdSeedId((string) Str::uuid());
        $seed = HdSeed::create($id, $reference, $family, new DateTimeImmutable());

        // Шаг 3 (GUIDE §2): сохраняем доменный агрегат в `hd_seeds`. БД содержит только
        // метаданные (id, reference, family, created_at) — ни байта секретного материала.
        $this->db->transaction(function () use ($seed): void {
            $this->seeds->save($seed);
        });

        // Шаг 4 (GUIDE §2 + §5 «Поднятие событий после COMMIT»): событие HdSeedCreated
        // диспатчим строго ПОСЛЕ COMMIT — иначе при откате транзакции получим
        // «фантомное» событие про несуществующую запись.
        $events = $seed->pullPendingEvents();
        $this->db->afterCommit(function () use ($events): void {
            foreach ($events as $event) {
                $this->events->dispatch($event);
            }
        });

        return $id;
    }
}
