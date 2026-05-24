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
 * при обнаружении реорга в Фазе 5.
 *
 * Хеш родителя ДОЛЖЕН присутствовать даже для блоков, смежных с генезисом — Фаза 4
 * сканирует только не-генезис высоты, поэтому конструктор требует валидный BlockHash.
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
        if ($hash->equals($parentHash)) {
            throw new InvalidArgumentException(
                "Хеш блока и хеш родителя должны различаться на высоте {$height->value}."
            );
        }
    }

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
