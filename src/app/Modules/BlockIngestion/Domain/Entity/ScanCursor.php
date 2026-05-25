<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Entity;

use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Где находится сканер в данной сети — «закладка» в книге блокчейна.
 *
 * Концепция и инварианты — GUIDE.md, Урок 5 «Сканирование цепи»:
 *
 *  - `lastScannedHeight` — самая высокая высота, которую мы уже обработали;
 *    следующий тик попытается получить `lastScannedHeight + 1`.
 *  - `lastSeenHeadHeight` — наблюдаемая вершина сети на момент последнего опроса.
 *  - Инвариант: `lastScannedHeight <= lastSeenHeadHeight`.
 *  - hasPendingBlocks() = head > lastScanned — есть ли что догнать.
 *
 * ⚠️ Курсор продвигается строго по одному блоку (`advanceTo(+1)`).
 * Это упрощает обработку реоргов: при несовпадении parent_hash модуль
 * ReorgDetection делает rollback ровно на одну высоту назад, и следующий
 * тик скачивает уже новую версию того же блока (GUIDE.md, Урок 7).
 *
 * @see \GUIDE.md  Урок 5 (#урок-5--сканирование-цепи-и-обнаружение-поступлений)
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
 */
final class ScanCursor
{
    private function __construct(
        public readonly ChainId $chainId,
        private BlockHeight $lastScannedHeight,
        private BlockHeight $lastSeenHeadHeight,
        private DateTimeImmutable $updatedAt,
    ) {
        if ($lastScannedHeight->value > $lastSeenHeadHeight->value) {
            throw new InvalidArgumentException(
                "ScanCursor: lastScannedHeight ({$lastScannedHeight->value}) "
                ."cannot exceed lastSeenHeadHeight ({$lastSeenHeadHeight->value})."
            );
        }
    }

    public static function initialise(
        ChainId $chainId,
        BlockHeight $head,
        DateTimeImmutable $now,
    ): self {
        // Базовый уровень: предполагаем, что всё до `head` уже было просканировано.
        // Историческое back-fill не реализуем — сканер работает только на догон сверху.
        return new self($chainId, $head, $head, $now);
    }

    public static function reconstitute(
        ChainId $chainId,
        BlockHeight $lastScannedHeight,
        BlockHeight $lastSeenHeadHeight,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($chainId, $lastScannedHeight, $lastSeenHeadHeight, $updatedAt);
    }

    public function lastScannedHeight(): BlockHeight
    {
        return $this->lastScannedHeight;
    }

    public function lastSeenHeadHeight(): BlockHeight
    {
        return $this->lastSeenHeadHeight;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function observeHead(BlockHeight $head, DateTimeImmutable $now): void
    {
        if ($head->value < $this->lastScannedHeight->value) {
            // Возможный сигнал реорганизации — обработает модуль ReorgDetection.
            // Здесь мы держим курсор стабильным и не двигаем его назад сами.
            $this->updatedAt = $now;
            return;
        }
        $this->lastSeenHeadHeight = $head;
        $this->updatedAt = $now;
    }

    public function advanceTo(BlockHeight $height, DateTimeImmutable $now): void
    {
        if ($height->value !== $this->lastScannedHeight->value + 1) {
            throw new InvalidArgumentException(
                "ScanCursor must advance by exactly one block "
                ."(at {$this->lastScannedHeight->value}, tried {$height->value})."
            );
        }
        $this->lastScannedHeight = $height;
        if ($height->value > $this->lastSeenHeadHeight->value) {
            $this->lastSeenHeadHeight = $height;
        }
        $this->updatedAt = $now;
    }

    public function nextHeight(): BlockHeight
    {
        return $this->lastScannedHeight->next();
    }

    public function hasPendingBlocks(): bool
    {
        return $this->lastSeenHeadHeight->value > $this->lastScannedHeight->value;
    }
}
