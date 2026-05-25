<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use App\Modules\Withdrawal\Domain\Exception\InvalidWithdrawalStateTransitionException;

/**
 * Конечный автомат withdrawal (GUIDE.md, Урок 10 «State machine»):
 *
 *   Requested → Built → Signed → Broadcasted → Confirming → Confirmed
 *                                          │
 *                                          ├─→ Stuck → Replaced (RBF, Урок 11)
 *                                          └─→ Failed
 *
 * Терминальные: Confirmed, Failed, Replaced.
 *
 * ⚠️ Каждый переход охраняется {@see assertCanTransitionTo()}. Это инварианты,
 * которые делают Withdrawal настоящим агрегатом, а не CRUD-моделью: пропуск
 * шага (например, Requested → Broadcasted) — баг, а не «гибкость».
 *
 * @see \GUIDE.md  Урок 10 (#урок-10--вывод-средств-withdrawal)
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
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
