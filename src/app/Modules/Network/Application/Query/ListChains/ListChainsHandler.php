<?php

declare(strict_types=1);

namespace App\Modules\Network\Application\Query\ListChains;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;

final readonly class ListChainsHandler
{
    public function __construct(private ChainRepository $chains) {}

    /**
     * @return list<ChainView>
     */
    public function handle(bool $onlyEnabled = false): array
    {
        $chains = $onlyEnabled ? $this->chains->allEnabled() : $this->chains->all();

        return array_map($this->toView(...), $chains);
    }

    private function toView(Chain $chain): ChainView
    {
        return new ChainView(
            id: $chain->id->value,
            name: (string) $chain->name,
            family: $chain->family->value,
            currencySymbol: $chain->nativeCurrency->symbol,
            requiredConfirmations: $chain->confirmationRequirement->requiredConfirmations,
            maxReorgDepth: $chain->confirmationRequirement->maxReorgDepth,
            enabled: $chain->isEnabled(),
            endpoints: array_map(
                fn (RpcEndpoint $e): array => [
                    'url' => $e->url,
                    'kind' => $e->kind->value,
                    'priority' => $e->priority,
                    'weight' => $e->weight,
                ],
                $chain->endpoints(),
            ),
        );
    }
}
