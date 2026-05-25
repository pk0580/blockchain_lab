<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Service;

use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;
use InvalidArgumentException;

/**
 * Чистая функция вычисления подтверждений — без побочных эффектов, легко тестируется.
 *
 * Формула (GUIDE.md, Урок 6 «Что значит "подтверждение"»):
 *
 *   confirmations = max(0, lastScannedHeight - txBlockHeight + 1)
 *
 *   outcome = Finalized  если confirmations > maxReorgDepth
 *           = Confirmed  если confirmations >= requiredConfirmations
 *           = Confirming иначе (>= 1, т.к. tx в просканированном блоке)
 *
 * @see \GUIDE.md  Урок 6 (#урок-6--подтверждения-и-финализация)
 */
final class ConfirmationCalculator
{
    public function compute(
        int $txBlockHeight,
        int $lastScannedHeight,
        int $requiredConfirmations,
        int $maxReorgDepth,
    ): ConfirmationStep {
        if ($requiredConfirmations < 1) {
            throw new InvalidArgumentException(
                "requiredConfirmations must be >= 1, got {$requiredConfirmations}."
            );
        }
        if ($maxReorgDepth < $requiredConfirmations) {
            throw new InvalidArgumentException(
                "maxReorgDepth ({$maxReorgDepth}) must be >= requiredConfirmations ({$requiredConfirmations})."
            );
        }

        $confirmations = max(0, $lastScannedHeight - $txBlockHeight + 1);
        $outcome = match (true) {
            $confirmations > $maxReorgDepth        => ConfirmationOutcome::Finalized,
            $confirmations >= $requiredConfirmations => ConfirmationOutcome::Confirmed,
            default                                => ConfirmationOutcome::Confirming,
        };

        return new ConfirmationStep($confirmations, $outcome);
    }
}
