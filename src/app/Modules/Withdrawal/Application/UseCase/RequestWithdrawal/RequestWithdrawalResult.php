<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal;

use App\Modules\Withdrawal\Domain\Entity\Withdrawal;

/**
 * Результат use-case'а. Возвращаем сам aggregate (готовый к проекции в HTTP
 * Resource), а также флаг `reused`, чтобы UI мог выдать 200 при идемпотентном
 * replay vs 202 при новой записи.
 */
final readonly class RequestWithdrawalResult
{
    public function __construct(
        public Withdrawal $withdrawal,
        public bool $reused,
    ) {}
}
