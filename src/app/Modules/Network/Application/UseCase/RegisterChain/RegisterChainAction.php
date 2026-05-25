<?php

declare(strict_types=1);

namespace App\Modules\Network\Application\UseCase\RegisterChain;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\ChainAlreadyRegisteredException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

/**
 * Регистрация новой блокчейн-сети в реестре.
 *
 * Бизнес-выгода clean-architecture (GUIDE.md, Урок 4): чтобы добавить новую
 * EVM-сеть (Arbitrum, Base, очередной L2-роллап), достаточно одного вызова
 * этого Action — никакого нового PHP-кода. Адаптер семейства EVM
 * ({@see EvmAdapter}) уже умеет с ней работать через RPC.
 *
 * @see \GUIDE.md  Урок 4 (#урок-4--l1-l2-и-семейства-сетей)
 */
final readonly class RegisterChainAction
{
    public function __construct(
        private ChainRepository $chains,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(RegisterChainData $data): ChainId
    {
        $chainId = new ChainId($data->chainId);

        if ($this->chains->existsById($chainId)) {
            throw ChainAlreadyRegisteredException::byId($chainId);
        }

        $endpoints = array_map(
            fn (array $e): RpcEndpoint => new RpcEndpoint(
                url: $e['url'],
                kind: RpcKind::from($e['kind']),
                priority: $e['priority'] ?? 100,
                weight: $e['weight'] ?? 1,
            ),
            $data->endpoints,
        );

        $chain = Chain::register(
            id: $chainId,
            name: new ChainName($data->name),
            family: ChainFamily::fromString($data->family),
            nativeCurrency: new NativeCurrency($data->currencySymbol, $data->currencyDecimals),
            confirmationRequirement: new ConfirmationRequirement(
                $data->requiredConfirmations,
                $data->maxReorgDepth,
            ),
            endpoints: $endpoints,
            now: new DateTimeImmutable(),
        );

        if ($data->enable) {
            $chain->enable(new DateTimeImmutable());
        }

        $this->db->transaction(function () use ($chain): void {
            $this->chains->save($chain);
        });

        $events = $chain->pullPendingEvents();
        $this->db->afterCommit(function () use ($events): void {
            foreach ($events as $event) {
                $this->events->dispatch($event);
            }
        });

        return $chainId;
    }
}
