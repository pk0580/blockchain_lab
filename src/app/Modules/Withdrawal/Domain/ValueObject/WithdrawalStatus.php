<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use App\Modules\Withdrawal\Domain\Exception\InvalidWithdrawalStateTransitionException;

/**
 * Конечный автомат состояний withdrawal. Phase 6.2 покрывает прямую ветку:
 *
 *   Requested → Built → Signed → Broadcasted
 *
 * Любой шаг может уйти в `Failed`. Состояния `Confirming/Confirmed/Stuck/Replaced`
 * добавит Phase 6.3 (poller + RBF/resend), они уже зарезервированы в enum,
 * чтобы migration схемы был стабилен и аппаратные тесты на forbidden transitions
 * не пересоздавались.
 */
enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Built = 'built';
    case Signed = 'signed';
    case Broadcasted = 'broadcasted';
    case Confirming = 'confirming';
    case Confirmed = 'confirmed';
    case Stuck = 'stuck';
    case Replaced = 'replaced';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Confirmed, self::Failed, self::Replaced => true,
            default => false,
        };
    }

    /**
     * Проверяет легальность перехода. Раскрытие сюда централизует state-машину
     * и держит логику близко к данным.
     *
     * @throws InvalidWithdrawalStateTransitionException
     */
    public function assertCanTransitionTo(self $next): void
    {
        $allowed = match ($this) {
            self::Requested => [self::Built, self::Failed],
            self::Built => [self::Signed, self::Failed],
            self::Signed => [self::Broadcasted, self::Failed],
            self::Broadcasted => [self::Confirming, self::Confirmed, self::Stuck, self::Failed],
            self::Confirming => [self::Confirmed, self::Stuck, self::Failed],
            self::Stuck => [self::Replaced, self::Failed],
            self::Confirmed, self::Failed, self::Replaced => [],
        };

        if (! in_array($next, $allowed, strict: true)) {
            throw InvalidWithdrawalStateTransitionException::between($this, $next);
        }
    }
}
