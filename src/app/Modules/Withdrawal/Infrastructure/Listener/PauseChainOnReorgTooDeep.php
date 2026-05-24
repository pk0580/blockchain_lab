<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Listener;

use App\Modules\ReorgDetection\Domain\Event\ReorgTooDeep;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;

/**
 * Слушает чужой domain event `ReorgDetection::Domain::Event::ReorgTooDeep` и
 * ставит сеть на паузу для исходящих withdrawal'ов на 24 часа (config-driven).
 *
 * Импорт чужого Domain допустим только здесь — это Infrastructure-листенер,
 * мост между bounded contexts (см. паттерн в Ledger::Infrastructure\Listener\ReverseLedgerOnReorg).
 * Application-актион никогда не должен импортировать ReorgDetection.
 */
final readonly class PauseChainOnReorgTooDeep
{
    public function __construct(
        private ChainPauseRegistry $pauses,
        private ConfigRepository $config,
    ) {}

    public function handle(ReorgTooDeep $event): void
    {
        $ttl = (int) $this->config->get('withdrawal.chain_pause_ttl_seconds', 86400);
        $this->pauses->pause($event->chainId, $ttl);

        Log::warning('withdrawal.chain.paused', [
            'chain_id' => $event->chainId->value,
            'orphaned_height' => $event->orphanedHeight->value,
            'observed_depth' => $event->observedDepth,
            'threshold' => $event->threshold,
            'ttl_seconds' => $ttl,
        ]);
    }
}
