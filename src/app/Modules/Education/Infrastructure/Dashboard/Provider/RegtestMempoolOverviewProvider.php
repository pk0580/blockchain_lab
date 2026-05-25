<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Dashboard\Provider;

use App\Modules\Education\Application\Contract\Dashboard\MempoolOverviewProvider;
use App\Modules\Education\Application\Contract\RegtestRpcClient;
use App\Modules\Education\Application\DTO\Dashboard\MempoolRow;
use App\Modules\Education\Application\DTO\Dashboard\MempoolSection;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use Throwable;

/**
 * Mempool снимок per-chain. Реализован только для Bitcoin regtest
 * (`getrawmempool` — список tx_id, считаем их количество).
 *
 * EVM/Tron строки тоже включаем, но `txCount=null` + поясняющий `error`,
 * чтобы admin видел, что сеть в системе, но mempool ещё не подключен.
 * Это явно — лучше пустого пробела на UI.
 *
 * Если bitcoind недоступен — пишем error и `txCount=null` (не падаем).
 */
final readonly class RegtestMempoolOverviewProvider implements MempoolOverviewProvider
{
    public function __construct(
        private ChainRepository $chains,
        private RegtestRpcClient $bitcoinRegtest,
    ) {}

    public function load(): MempoolSection
    {
        $rows = [];
        foreach ($this->chains->all() as $chain) {
            if ($chain->family === ChainFamily::Bitcoin) {
                $rows[] = $this->bitcoinRow(
                    chainId: $chain->id->value,
                    chainName: $chain->name->value,
                );
                continue;
            }
            $rows[] = new MempoolRow(
                chainId: $chain->id->value,
                chainName: $chain->name->value,
                chainFamily: $chain->family->value,
                txCount: null,
                error: 'mempool snapshot not exposed at this layer for '.$chain->family->value,
            );
        }
        return new MempoolSection($rows);
    }

    private function bitcoinRow(string $chainId, string $chainName): MempoolRow
    {
        try {
            $count = count($this->bitcoinRegtest->getRawMempool());
            return new MempoolRow(
                chainId: $chainId,
                chainName: $chainName,
                chainFamily: ChainFamily::Bitcoin->value,
                txCount: $count,
                error: null,
            );
        } catch (Throwable $e) {
            return new MempoolRow(
                chainId: $chainId,
                chainName: $chainName,
                chainFamily: ChainFamily::Bitcoin->value,
                txCount: null,
                error: 'rpc_unreachable: '.$e->getMessage(),
            );
        }
    }
}
