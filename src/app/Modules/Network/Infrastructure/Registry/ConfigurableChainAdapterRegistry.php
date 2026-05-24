<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Registry;

use App\Modules\Network\Domain\Contract\ChainAdapter;
use App\Modules\Network\Domain\Contract\ChainAdapterRegistry;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use Closure;
use RuntimeException;

/**
 * Содержит одну фабрику ChainAdapter на каждое семейство сетей (ChainFamily). Во время разрешения (resolve)
 * фабрике передается загруженный агрегат Chain, поэтому одна и та же фабрика EvmAdapter создает
 * адаптер Sepolia и адаптер Polygon-Amoy на основе различных записей Chain.
 */
final class ConfigurableChainAdapterRegistry implements ChainAdapterRegistry
{
    /** @var array<string, Closure> */
    private array $factories = [];

    public function __construct(private readonly ChainRepository $chains) {}

    /**
     * @param Closure(\App\Modules\Network\Domain\Entity\Chain): ChainAdapter $factory
     */
    public function registerFamily(ChainFamily $family, Closure $factory): void
    {
        $this->factories[$family->value] = $factory;
    }

    public function adapterFor(ChainId $chainId): ChainAdapter
    {
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $factory = $this->factories[$chain->family->value]
            ?? throw new RuntimeException(
                "Для семейства '{$chain->family->value}' не зарегистрирована фабрика ChainAdapter."
            );

        return $factory($chain);
    }

    /**
     * @return list<ChainAdapter>
     */
    public function enabledAdapters(): array
    {
        $adapters = [];
        foreach ($this->chains->allEnabled() as $chain) {
            $factory = $this->factories[$chain->family->value] ?? null;
            if ($factory !== null) {
                $adapters[] = $factory($chain);
            }
        }
        return $adapters;
    }
}
