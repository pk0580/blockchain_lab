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
        $reference = new HdSeedReference($data->reference);
        $family = $data->family !== null ? ChainFamily::fromString($data->family) : null;

        // signing-svc является первоисточником данных о том, существует ли этот сид.
        // Он идемпотентен: передача существующей ссылки возвращает created=false.
        $this->signing->ensureSeed((string) $reference, $data->importMnemonic);

        $id = new HdSeedId((string) Str::uuid());
        $seed = HdSeed::create($id, $reference, $family, new DateTimeImmutable());

        $this->db->transaction(function () use ($seed): void {
            $this->seeds->save($seed);
        });

        $events = $seed->pullPendingEvents();
        $this->db->afterCommit(function () use ($events): void {
            foreach ($events as $event) {
                $this->events->dispatch($event);
            }
        });

        return $id;
    }
}
