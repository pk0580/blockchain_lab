<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Infrastructure\Listener;

use App\Modules\BlockIngestion\Domain\Event\BlockIngested;
use App\Modules\ReorgDetection\Application\UseCase\EvaluateBlockReorg\EvaluateBlockReorgAction;
use App\Modules\ReorgDetection\Application\UseCase\EvaluateBlockReorg\EvaluateBlockReorgData;

/**
 * Мост BlockIngestion → ReorgDetection: каждое сохранение нового блока
 * запускает компаратор. Слушает чужое доменное событие — это допустимая
 * нагрузка для Infrastructure-слоя (anti-corruption layer).
 *
 * Связь модулей описана в GUIDE.md, Урок 7 «Алгоритм обнаружения»: реакция
 * на событие `BlockIngested` — это входная точка цепочки модулей ReorgDetection
 * → Ledger::ReverseLedgerOnReorg / Webhook::RecordOutboxOnReorgDetected /
 * Withdrawal::PauseChainOnReorgTooDeep.
 *
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
 */
final readonly class EvaluateOnBlockIngested
{
    public function __construct(private EvaluateBlockReorgAction $action) {}

    public function handle(BlockIngested $event): void
    {
        $this->action->handle(new EvaluateBlockReorgData(
            chainId: $event->chainId->value,
            height: $event->height->value,
            hash: $event->hash->normalized(),
            parentHash: $event->parentHash->normalized(),
        ));
    }
}
