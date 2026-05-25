<?php

declare(strict_types=1);

namespace App\Modules\Fee\Infrastructure\Estimator;

use App\Modules\Fee\Domain\Contract\FeeEstimator;
use App\Modules\Fee\Domain\Exception\FeeEstimationFailedException;
use App\Modules\Fee\Domain\ValueObject\BitcoinFeeBreakdown;
use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Fee\Domain\ValueObject\FeeQuote;
use App\Modules\Fee\Infrastructure\Rpc\BitcoinFeeRpcClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;
use RuntimeException;

/**
 * Bitcoin fee estimator поверх `estimatesmartfee` (GUIDE.md, Урок 9 «Bitcoin: sat/vbyte»).
 *
 * Алгоритм:
 *  1. {@see FeePriority} → (target blocks, mode):
 *     Low      → conservative, дальний target
 *     Standard → economical/conservative, средний target
 *     High     → economical, ближний target
 *  2. RPC `estimatesmartfee(target, mode)` → feerate в BTC/kB.
 *  3. Конвертация: `sat_per_vbyte = ceil(btc_per_kb * 1e8 / 1000)`.
 *     ⚠️ Через bcmath, не float (теряется точность на больших суммах).
 *  4. Если RPC вернул -1 (нет данных у ноды — типично на пустом regtest)
 *     → fallback на `min_sat_per_vbyte` из конфига.
 *
 * Итоговая комиссия = `sat_per_vbyte * vsize`, где vsize оценивает {@see BitcoinTxBuilder}
 * по эвристике 110·inputs + 34·outputs + 10.
 *
 * @see \GUIDE.md  Урок 9 (#урок-9--комиссия-fee)
 */
final readonly class BitcoinFeeEstimator implements FeeEstimator
{
    public function __construct(
        private BitcoinFeeRpcClient $rpc,
        /** @var array{low: int, standard: int, high: int} */
        private array $targets,
        /** @var array{low: string, standard: string, high: string} */
        private array $modes,
        private int $minSatPerVbyte,
    ) {}

    public function estimate(Chain $chain, FeePriority $priority): FeeQuote
    {
        if ($chain->family !== ChainFamily::Bitcoin) {
            throw new RuntimeException(
                "BitcoinFeeEstimator received non-bitcoin chain '{$chain->id->value}' (family={$chain->family->value})."
            );
        }

        $target = match ($priority) {
            FeePriority::Low => $this->targets['low'],
            FeePriority::Standard => $this->targets['standard'],
            FeePriority::High => $this->targets['high'],
        };
        $mode = match ($priority) {
            FeePriority::Low => $this->modes['low'],
            FeePriority::Standard => $this->modes['standard'],
            FeePriority::High => $this->modes['high'],
        };

        $url = $this->httpEndpointUrl($chain);
        $raw = $this->rpc->estimateSmartFee($chain->id, $url, $target, $mode);

        return new FeeQuote(
            chainId: $chain->id,
            priority: $priority,
            breakdown: new BitcoinFeeBreakdown($this->convertFeerate($raw)),
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
            "chain '{$chain->id->value}' has no HTTP RPC endpoint"
        );
    }

    /**
     * @param array{feerate?: float|string|int, blocks?: int, errors?: list<string>} $raw
     */
    private function convertFeerate(array $raw): int
    {
        $feerate = $raw['feerate'] ?? null;
        if ($feerate === null || ! is_numeric($feerate) || (float) $feerate < 0) {
            return $this->minSatPerVbyte;
        }

        // feerate приходит как BTC/kB. Конвертация без float-дрейфа через bcmath:
        // satPerVbyte = ceil(BTC_per_kB * 1e8 / 1000).
        /** @var numeric-string $feerateStr */
        $feerateStr = is_string($feerate) ? $feerate : sprintf('%.8F', (float) $feerate);
        $satPerKb = bcmul($feerateStr, '100000000', 0);
        $satPerVbyte = (int) bcdiv($satPerKb, '1000', 0);

        $remainder = bcmod($satPerKb, '1000');
        if ($remainder !== '0') {
            $satPerVbyte++;
        }

        return max($satPerVbyte, $this->minSatPerVbyte);
    }
}
