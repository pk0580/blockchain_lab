<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Service;

use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;
use InvalidArgumentException;

/**
 * Чистая функция. По высоте последнего просканированного блока и высоте
 * блока tx возвращает текущее число подтверждений и его жизненный outcome
 * для сети с порогом `$requiredConfirmations` и пределом реорга `$maxReorgDepth`.
 *
 *   confirmations = max(0, lastScannedHeight - txHeight + 1)
 *   outcome       = finalized  если confirmations > maxReorgDepth
 *                   confirmed  если confirmations >= requiredConfirmations
 *                   confirming иначе (>= 1, т.к. tx в просканированном блоке)
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
