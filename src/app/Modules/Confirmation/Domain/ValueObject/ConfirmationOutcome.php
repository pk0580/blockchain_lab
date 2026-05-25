<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\ValueObject;

/**
 * Состояния входящей транзакции в жизненном цикле подтверждений.
 *
 * Семантика каждого состояния (GUIDE.md, Урок 6 — раздел «Состояния транзакции»):
 *
 *  - Detected   — нашли в блоке, ещё не считали подтверждения.
 *  - Confirming — есть подтверждения, но меньше requiredConfirmations.
 *  - Confirmed  — подтверждений ≥ requiredConfirmations. Момент, когда
 *                 зачисляем на баланс (Ledger).
 *  - Finalized  — подтверждений > maxReorgDepth. Откатить считается
 *                 практически невозможным.
 *  - Orphaned   — блок попал в reorg; ставится модулем ReorgDetection,
 *                 Confirmation её не трогает (Урок 7).
 *
 * @see \GUIDE.md  Урок 6 (#урок-6--подтверждения-и-финализация)
 */
enum ConfirmationOutcome: string
{
    case Detected = 'detected';
    case Confirming = 'confirming';
    case Confirmed = 'confirmed';
    case Finalized = 'finalized';
    case Orphaned = 'orphaned';
}
