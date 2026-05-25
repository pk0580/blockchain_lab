<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Entity;

use App\Modules\BlockIngestion\Domain\Event\BlockIngested;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Один обработанный блок в конкретном блокчейне. Идентифицируется по (chainId, height)
 * внутри границ агрегата; хеш является естественным альтернативным ключом, используемым
 * при обнаружении реорга.
 *
 * Хеш родителя ДОЛЖЕН присутствовать даже для блоков, смежных с генезисом — сканер
 * не обрабатывает генезис, поэтому конструктор требует валидный BlockHash.
 *
 * Концепция «что такое блок и почему он ссылается на родителя» — см. GUIDE.md,
 * Урок 1 «Что такое блокчейн». Использование parent_hash для обнаружения реорга
 * описано в GUIDE.md, Урок 7 «Реорганизации цепи».
 *
 * @see \GUIDE.md  Урок 1 (#урок-1--что-такое-блокчейн)
 */
final class Block
{
    /** @var list<object> */
    private array $pendingEvents = [];

    private function __construct(
        public readonly ChainId $chainId,
        public readonly BlockHeight $height,
        public readonly BlockHash $hash,
        public readonly BlockHash $parentHash,
        public readonly DateTimeImmutable $timestamp,
        public readonly DateTimeImmutable $scannedAt,
    ) {
        // Инвариант: блок не может ссылаться сам на себя как на родителя.
        // Совпадение хешей — индикатор повреждения данных RPC или ошибки маппера.
        if ($hash->equals($parentHash)) {
            throw new InvalidArgumentException(
                "Хеш блока и хеш родителя должны различаться на высоте {$height->value}."
            );
        }
    }

    /**
     * Фабрика для нового, только что сканированного блока.
     *
     * Поднимает доменное событие {@see BlockIngested}, которое после COMMIT
     * слушают: модуль Confirmation (пересчёт подтверждений) и модуль ReorgDetection
     * (сравнение parent_hash с уже сохранённой версией предыдущей высоты).
     * Подробнее о пайплайне — GUIDE.md, Урок 5 «Сканирование цепи».
     */
    public static function ingest(
        ChainId $chainId,
        BlockHeight $height,
        BlockHash $hash,
        BlockHash $parentHash,
        DateTimeImmutable $timestamp,
        DateTimeImmutable $scannedAt,
    ): self {
        $block = new self($chainId, $height, $hash, $parentHash, $timestamp, $scannedAt);
        $block->pendingEvents[] = new BlockIngested(
            chainId: $chainId,
            height: $height,
            hash: $hash,
            parentHash: $parentHash,
            occurredAt: $scannedAt,
        );
        return $block;
    }

    public static function reconstitute(
        ChainId $chainId,
        BlockHeight $height,
        BlockHash $hash,
        BlockHash $parentHash,
        DateTimeImmutable $timestamp,
        DateTimeImmutable $scannedAt,
    ): self {
        return new self($chainId, $height, $hash, $parentHash, $timestamp, $scannedAt);
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
}
