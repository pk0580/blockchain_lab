<?php

declare(strict_types=1);

namespace App\Modules\Fee\Application\UseCase\EstimateFee;

use App\Modules\Fee\Domain\Contract\FeeEstimatorRegistry;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;

/**
 * Тонкий оркестратор: загружает Chain из репозитория, делегирует выполнение
 * соответствующему оценщику (estimator) семейства и возвращает {@see FeeQuote}.
 *
 * Withdrawal::Application (Фаза 6.2) будет вызывать это действие напрямую —
 * взаимодействие Application↔Application между модулями допустимо, в то время как
 * Application↔Domain — нет.
 */
final readonly class EstimateFeeAction
{
    public function __construct(
        private ChainRepository $chains,
        private FeeEstimatorRegistry $registry,
    ) {}

    public function handle(EstimateFeeData $data): EstimateFeeResult
    {
        $chain = $this->chains->findById($data->chainId)
            ?? throw ChainNotFoundException::byId($data->chainId);

        $estimator = $this->registry->for($chain->family);
        $quote = $estimator->estimate($chain, $data->priority);

        return new EstimateFeeResult($quote);
    }
}
