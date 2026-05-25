<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Listener;

use App\Modules\ReorgDetection\Domain\Event\ReorgTooDeep;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;

/**
 * Мост ReorgDetection::ReorgTooDeep → Withdrawal::ChainPause.
 *
 * Это «human alert»: автоматический отказ от исходящих withdrawal'ов на сеть,
 * история которой только что переписалась глубже допустимого порога.
 *
 * Логика и обоснование — GUIDE.md, Урок 7 «Реакция других модулей» и
 * Урок 11 «Пауза сети». Пока пауза активна, RequestWithdrawalAction бросит
 * ChainPausedException (HTTP 503), пока инженер не разберётся.
 *
 * TTL = 24 часа по умолчанию (`withdrawal.chain_pause_ttl_seconds`).
 *
 * Импорт чужого Domain допустим только в Infrastructure-листенере —
 * Application-актион никогда не должен импортировать ReorgDetection.
 *
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
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
