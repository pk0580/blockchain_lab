<?php

declare(strict_types=1);

namespace Database\Seeders\Network;

use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainData;
use App\Modules\Network\Domain\Exception\ChainAlreadyRegisteredException;
use Illuminate\Database\Seeder;

final class DefaultChainsSeeder extends Seeder
{
    public function run(RegisterChainAction $register): void
    {
        $chains = [
            new RegisterChainData(
                chainId: 'bitcoin-regtest',
                name: 'Bitcoin (regtest)',
                family: 'bitcoin',
                currencySymbol: 'BTC',
                currencyDecimals: 8,
                requiredConfirmations: 1,        // regtest локальный — быстрая финальность
                maxReorgDepth: 100,
                endpoints: [[
                    'url' => (string) config('network.rpc.bitcoin_regtest'),
                    'kind' => 'http',
                ]],
            ),
            new RegisterChainData(
                chainId: 'ethereum-sepolia',
                name: 'Ethereum Sepolia',
                family: 'evm',
                currencySymbol: 'ETH',
                currencyDecimals: 18,
                requiredConfirmations: 12,
                maxReorgDepth: 64,
                endpoints: [[
                    'url' => (string) config('network.rpc.ethereum_sepolia'),
                    'kind' => 'http',
                ]],
            ),
            new RegisterChainData(
                chainId: 'tron-shasta',
                name: 'Tron Shasta',
                family: 'tron',
                currencySymbol: 'TRX',
                currencyDecimals: 6,
                requiredConfirmations: 19,       // окно необратимого блока SR (Super Representative)
                maxReorgDepth: 27,
                endpoints: [[
                    'url' => (string) config('network.rpc.tron_shasta'),
                    'kind' => 'http',
                ]],
            ),
            new RegisterChainData(
                chainId: 'polygon-amoy',
                name: 'Polygon Amoy',
                family: 'evm',
                currencySymbol: 'POL',
                currencyDecimals: 18,
                requiredConfirmations: 128,      // финальность в Polygon медленная
                maxReorgDepth: 256,
                endpoints: [[
                    'url' => (string) config('network.rpc.polygon_amoy'),
                    'kind' => 'http',
                ]],
            ),
        ];

        foreach ($chains as $data) {
            try {
                $register->handle($data);
            } catch (ChainAlreadyRegisteredException) {
                // идемпотентный сидер
            }
        }
    }
}
