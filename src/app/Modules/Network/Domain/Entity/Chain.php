<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Entity;

use App\Modules\Network\Domain\Event\ChainEnabled;
use App\Modules\Network\Domain\Event\ChainRegistered;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Корень агрегата (Aggregate root) для зарегистрированной блокчейн-сети.
 *
 * Хранит метаданные конкретной сети: {@see ChainId}, {@see ChainName},
 * {@see ChainFamily}, {@see NativeCurrency} (BTC, ETH, MATIC, …),
 * {@see ConfirmationRequirement} и список {@see RpcEndpoint}.
 *
 * RpcEndpoint — VO внутри границ агрегата; Chain владеет списком и обеспечивает
 * инвариант «как минимум один эндпоинт» (без RPC сеть бесполезна).
 *
 * Концепция «семейство vs сеть» подробно описана в GUIDE.md, Урок 4.
 *
 * @see \GUIDE.md  Урок 4 (#урок-4--l1-l2-и-семейства-сетей)
 */
final class Chain
{
    /** @var list<RpcEndpoint> */
    private array $endpoints;

    /** @var list<object> */
    private array $pendingEvents = [];

    /**
     * @param list<RpcEndpoint> $endpoints
     */
    private function __construct(
        public readonly ChainId $id,
        public readonly ChainName $name,
        public readonly ChainFamily $family,
        public readonly NativeCurrency $nativeCurrency,
        public readonly ConfirmationRequirement $confirmationRequirement,
        array $endpoints,
        private bool $enabled,
        public readonly DateTimeImmutable $registeredAt,
    ) {
        $this->setEndpoints($endpoints);
    }

    /**
     * @param list<RpcEndpoint> $endpoints
     */
    public static function register(
        ChainId $id,
        ChainName $name,
        ChainFamily $family,
        NativeCurrency $nativeCurrency,
        ConfirmationRequirement $confirmationRequirement,
        array $endpoints,
        DateTimeImmutable $now,
    ): self {
        $chain = new self(
            id: $id,
            name: $name,
            family: $family,
            nativeCurrency: $nativeCurrency,
            confirmationRequirement: $confirmationRequirement,
            endpoints: $endpoints,
            enabled: false,
            registeredAt: $now,
        );

        $chain->pendingEvents[] = new ChainRegistered($id, $family, $now);

        return $chain;
    }

    /**
     * @param list<RpcEndpoint> $endpoints
     */
    public static function reconstitute(
        ChainId $id,
        ChainName $name,
        ChainFamily $family,
        NativeCurrency $nativeCurrency,
        ConfirmationRequirement $confirmationRequirement,
        array $endpoints,
        bool $enabled,
        DateTimeImmutable $registeredAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            family: $family,
            nativeCurrency: $nativeCurrency,
            confirmationRequirement: $confirmationRequirement,
            endpoints: $endpoints,
            enabled: $enabled,
            registeredAt: $registeredAt,
        );
    }

    public function enable(DateTimeImmutable $now): void
    {
        if ($this->enabled) {
            return;
        }
        $this->enabled = true;
        $this->pendingEvents[] = new ChainEnabled($this->id, $now);
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list<RpcEndpoint>
     */
    public function endpoints(): array
    {
        return $this->endpoints;
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

    /**
     * @param list<RpcEndpoint> $endpoints
     */
    private function setEndpoints(array $endpoints): void
    {
        if ($endpoints === []) {
            throw new InvalidArgumentException(
                "Сеть '{$this->id->value}' требует наличия хотя бы одного RPC-эндпоинта."
            );
        }
        $this->endpoints = $endpoints;
    }
}
