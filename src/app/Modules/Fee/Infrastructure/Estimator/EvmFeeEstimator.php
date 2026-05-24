<?php

declare(strict_types=1);

namespace App\Modules\Fee\Infrastructure\Estimator;

use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Exception\FeeEstimationFailedException;
use App\Modules\Fee\Domain\ValueObject\EvmFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Fee\Infrastructure\Rpc\EvmRpcClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;
use RuntimeException;

/**
 * Оценщик (estimator) по стандарту EIP-1559:
 *  - Получает `eth_feeHistory` за {@see self::HISTORY_BLOCKS} последних блоков для
 *    одного процентиля (10/50/90 для Low/Standard/High).
 *  - baseFee = последний `baseFeePerGas` (это baseFee для следующего блока).
 *  - priorityFee = среднее ненулевых сэмплов процентиля в окне (отсеиваем 0,
 *    которые приходят на пустых блоках testnet).
 *  - maxFeePerGas = baseFee * multiplier + priorityFee
 *    (запас на случай до двух подряд полных блоков, где baseFee увеличивается на 12.5%).
 *
 * gasLimit для перевода нативного актива = 21000 (фиксированный протокольный минимум).
 * ERC-20 и сложные контракты — отдельная логика в Фазе 8+.
 */
final readonly class EvmFeeEstimator implements FeeEstimator
{
    private const HISTORY_BLOCKS = 4;
    private const NEWEST_BLOCK = 'latest';

    public function __construct(
        private EvmRpcClient $rpc,
        /** @var array{low: int, standard: int, high: int} */
        private array $priorityPercentiles,
        private float $baseFeeMultiplier,
        private int $gasLimitTransfer,
    ) {}

    public function estimate(Chain $chain, FeePriority $priority): FeeQuote
    {
        if ($chain->family !== ChainFamily::Evm) {
            throw new RuntimeException(
                "EvmFeeEstimator получил сеть, отличную от EVM: '{$chain->id->value}' (family={$chain->family->value})."
            );
        }

        $percentile = match ($priority) {
            FeePriority::Low => $this->priorityPercentiles['low'],
            FeePriority::Standard => $this->priorityPercentiles['standard'],
            FeePriority::High => $this->priorityPercentiles['high'],
        };

        $url = $this->httpEndpointUrl($chain);

        $history = $this->rpc->feeHistory(
            $chain->id,
            $url,
            self::HISTORY_BLOCKS,
            self::NEWEST_BLOCK,
            [$percentile],
        );

        $baseFees = $history['base_fees_wei'];
        $rewards = $history['rewards_wei'];

        if ($baseFees === []) {
            throw FeeEstimationFailedException::unusableResponse(
                $chain->id,
                'eth_feeHistory вернул пустой список base_fees'
            );
        }

        // Последний элемент baseFeePerGas — baseFee следующего (pending) блока.
        $nextBaseFee = $baseFees[array_key_last($baseFees)];

        $priorityWei = $this->averagePriority($rewards);

        $bumpedBaseFee = bcmul($nextBaseFee, $this->multiplierToString(), 0);
        $maxFeePerGas = bcadd($bumpedBaseFee, $priorityWei, 0);

        // Защита от 0-fee: на пустом регрессионном тестнете baseFee может быть 0,
        // тогда maxFee=priority. Минимальный sane fee = 1 wei, чтобы транзакцию не отклонили.
        if (bccomp($maxFeePerGas, '0', 0) === 0) {
            $maxFeePerGas = '1';
            $priorityWei = '1';
        }
        /** @var numeric-string $maxFeePerGas */
        /** @var numeric-string $priorityWei */
        // EIP-1559: priority <= maxFee. Если baseFee провалился ниже priority,
        // подтянем maxFee.
        if (bccomp($priorityWei, $maxFeePerGas, 0) > 0) {
            $maxFeePerGas = $priorityWei;
        }

        return new FeeQuote(
            chainId: $chain->id,
            priority: $priority,
            breakdown: new EvmFeeBreakdown(
                maxFeePerGasWei: $maxFeePerGas,
                maxPriorityFeePerGasWei: $priorityWei,
                gasLimit: $this->gasLimitTransfer,
            ),
            estimatedAt: new DateTimeImmutable(),
        );
    }

    private function httpEndpointUrl(Chain $chain): string
    {
        foreach ($chain->endpoints() as $endpoint) {
            if ($endpoint->kind === RpcKind::Http) {
                return $endpoint->url;
            }
        }
        throw FeeEstimationFailedException::unusableResponse(
            $chain->id,
            "сеть '{$chain->id->value}' не имеет HTTP RPC эндпоинта"
        );
    }

    /**
     * @param list<list<numeric-string>> $rewards
     * @return numeric-string
     */
    private function averagePriority(array $rewards): string
    {
        $sum = '0';
        $count = 0;
        foreach ($rewards as $row) {
            $first = $row[0] ?? null;
            if ($first === null) {
                continue;
            }
            if (bccomp($first, '0', 0) === 0) {
                continue;
            }
            $sum = bcadd($sum, $first, 0);
            $count++;
        }
        if ($count === 0) {
            return '1';
        }
        return bcdiv($sum, (string) $count, 0);
    }

    /**
     * @return numeric-string
     */
    private function multiplierToString(): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $this->baseFeeMultiplier), '0'), '.');
        // Если multiplier=0 или некорректен — возвращаем '1' (нейтральный).
        if (! is_numeric($formatted)) {
            return '1';
        }
        return $formatted;
    }
}
