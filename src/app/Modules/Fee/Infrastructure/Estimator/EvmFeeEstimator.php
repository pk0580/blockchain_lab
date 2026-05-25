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
 * EVM fee estimator по стандарту EIP-1559 (GUIDE.md, Урок 9 «EVM: EIP-1559»).
 *
 * Транзакция в EIP-1559 указывает:
 *   - max_fee_per_gas        — потолок (base_fee + priority, что вы готовы платить).
 *   - max_priority_fee_per_gas — tip майнеру/валидатору сверху.
 * Фактическая стоимость: `min(max_fee, base_fee + priority)`.
 *
 * Алгоритм:
 *  1. `eth_feeHistory(4, "latest", [percentile])` — 4 последних блока для
 *     перцентиля (10/50/90 на Low/Standard/High).
 *  2. base_fee = baseFeePerGas[последний] — это base fee следующего (pending) блока.
 *     ⚠️ Это уже посчитанное сетью значение (детерминированный гомеостаз).
 *  3. priority_fee = среднее ненулевых сэмплов перцентиля.
 *     Нули отбрасываем — это пустые блоки тестнета, неинформативные.
 *  4. max_fee_per_gas = base_fee * multiplier + priority_fee.
 *     ⚠️ multiplier ≈ 1.25 даёт запас на 2 подряд полных блока: 1.125² ≈ 1.27.
 *  5. Защита: если max_fee = 0 (пустой тестнет с base_fee=0, priority=0) →
 *     принудительно 1 wei, иначе нода отклонит.
 *
 * gas_limit для перевода нативного актива = 21000 (протокольный минимум).
 * ERC-20 и сложные контракты — отдельная логика, ещё не реализована.
 *
 * @see \GUIDE.md  Урок 9 (#урок-9--комиссия-fee)
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
