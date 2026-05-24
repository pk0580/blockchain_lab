<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Entity;

use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Где находится сканер в данной сети. `lastScannedHeight` — это
 * наибольшая высота, которая уже сохранена; следующий тик сканирования попытается получить
 * `lastScannedHeight + 1`. `lastSeenHeadHeight` — это наблюдаемая вершина
 * сети на момент самого последнего тика — используется модулем Confirmation для вычисления
 * того, насколько далеко отстал курсор.
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
        // Фаза 4 выполняет только прямое пополнение — историческое заполнение не входит в задачи.
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
            // Возможный сигнал реорганизации — Фаза 5 обработает это. Здесь мы просто держим
            // курсор стабильным и позволяем логике Фазы 5 взять на себя управление позже.
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
