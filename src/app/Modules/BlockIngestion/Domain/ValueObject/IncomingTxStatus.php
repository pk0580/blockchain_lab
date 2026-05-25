<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ValueObject;

use App\Modules\BlockIngestion\Domain\Exception\InvalidStatusTransitionException;

/**
 * Полный жизненный цикл inbound-транзакции на ватчинговый адрес.
 *
 *   detected   → первое появление tx в просканированном блоке.
 *   confirming → 1 <= confirmations < chain.required_confirmations.
 *   confirmed  → confirmations >= chain.required_confirmations.
 *   finalized  → блок tx ушёл глубже chain.max_reorg_depth, reorg больше невозможен.
 *   orphaned   → блок tx выпал из канонической цепи в результате реорганизации.
 *
 * Terminal: finalized. Orphaned ставит ReorgDetection — после ре-сканирования
 * новой версии блока tx может снова стать detected → confirming → confirmed.
 */
enum IncomingTxStatus: string
{
    case Detected = 'detected';
    case Confirming = 'confirming';
    case Confirmed = 'confirmed';
    case Finalized = 'finalized';
    case Orphaned = 'orphaned';

    /**
     * Terminal — состояние, из которого выход возможен только в результате
     * deep reorg (тогда дальше работает отдельный alert workflow, а не обычный
     * confirmation tick).
     */
    public function isTerminal(): bool
    {
        return $this === self::Finalized;
    }

    /**
     * Транзакция всё ещё в работе у Confirmation-модуля и должна пересчитываться
     * на каждый scan tick. Orphaned выпадает из обычного потока — её догоняет
     * BlockIngestion при повторном детекте, а не Confirmation.
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::Detected, self::Confirming, self::Confirmed => true,
            self::Finalized, self::Orphaned => false,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ([$this, $next]) {
            // Прямой прогресс по подтверждениям.
            [self::Detected, self::Confirming],
            [self::Detected, self::Confirmed],
            [self::Confirming, self::Confirmed],
            [self::Confirmed, self::Finalized],
            // Реорг-ветка — любой не-terminal статус может уйти в orphan.
            [self::Detected, self::Orphaned],
            [self::Confirming, self::Orphaned],
            [self::Confirmed, self::Orphaned],
            // Повторное обнаружение в новой канонической цепи.
            [self::Orphaned, self::Detected],
            [self::Orphaned, self::Confirming],
            [self::Orphaned, self::Confirmed] => true,
            default => false,
        };
    }

    public function assertCanTransitionTo(self $next): void
    {
        if (! $this->canTransitionTo($next)) {
            throw InvalidStatusTransitionException::from($this, $next);
        }
    }
}
