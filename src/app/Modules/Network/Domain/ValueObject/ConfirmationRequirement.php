<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Политика финализации для конкретной сети: сколько подтверждений нужно для
 * «достаточно надёжной» транзакции и насколько глубоко мы вообще согласны
 * автоматически откатывать историю.
 *
 * Типичные значения (GUIDE.md, Урок 4 — раздел «ConfirmationRequirement»):
 *   Bitcoin   → required = 6,  maxReorgDepth = 100
 *   Ethereum  → required = 12, maxReorgDepth = 64
 *   Polygon   → required = 64, maxReorgDepth = 128
 *
 * ⚠️ Инвариант `maxReorgDepth >= requiredConfirmations`. Иначе подтверждённую
 * транзакцию мы бы уже зачислили на баланс, но сеть всё ещё могла бы её
 * откатить — это противоречит. См. GUIDE.md, Урок 4 (конец раздела).
 *
 * @see \GUIDE.md  Урок 4 (#урок-4--l1-l2-и-семейства-сетей)
 * @see \GUIDE.md  Урок 6 (#урок-6--подтверждения-и-финализация)
 */
final readonly class ConfirmationRequirement
{
    public function __construct(
        public int $requiredConfirmations,
        public int $maxReorgDepth,
    ) {
        if ($requiredConfirmations < 1 || $requiredConfirmations > 1000) {
            throw new InvalidArgumentException("requiredConfirmations out of range: {$requiredConfirmations}.");
        }
        if ($maxReorgDepth < 1 || $maxReorgDepth > 10_000) {
            throw new InvalidArgumentException("maxReorgDepth out of range: {$maxReorgDepth}.");
        }
        // Инвариант, обоснованный в GUIDE §4: см. docblock выше.
        if ($maxReorgDepth < $requiredConfirmations) {
            throw new InvalidArgumentException(
                'maxReorgDepth must be >= requiredConfirmations '
                ."(got {$maxReorgDepth} < {$requiredConfirmations})."
            );
        }
    }

    public function isFinal(int $observedConfirmations): bool
    {
        return $observedConfirmations >= $this->requiredConfirmations;
    }
}
