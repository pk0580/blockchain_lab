<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\HotWallet;

use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Withdrawal\Application\Contract\HotWalletResolver;
use App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\HotWalletDescriptor;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;

/**
 * Резолвит hot wallet по `config('withdrawal.hot_wallets.{chain_id}')`.
 * Если `address` не задан явно — деривируется из signing-сервиса по
 * `hd_seed_ref` + `derivation_path`. Это даёт нам предсказуемое поведение
 * dev/staging без обязательного руками вписанного адреса.
 */
final readonly class ConfigHotWalletResolver implements HotWalletResolver
{
    public function __construct(
        private Config $config,
        private SigningClient $signing,
    ) {}

    public function resolve(Chain $chain): HotWalletDescriptor
    {
        $key = 'withdrawal.hot_wallets.'.$chain->id->value;
        $entry = $this->config->get($key);
        if (! is_array($entry)) {
            throw new RuntimeException(
                "Hot wallet for chain '{$chain->id->value}' is not configured (key '{$key}')."
            );
        }

        $seedReference = (string) ($entry['hd_seed_ref'] ?? '');
        $derivationPath = (string) ($entry['derivation_path'] ?? '');
        if ($seedReference === '' || $derivationPath === '') {
            throw new RuntimeException(
                "Hot wallet for '{$chain->id->value}' missing hd_seed_ref/derivation_path."
            );
        }

        $address = (string) ($entry['address'] ?? '');
        if ($address === '') {
            $address = (string) $this->signing->deriveAddress($seedReference, $chain->family, $derivationPath);
        }

        return new HotWalletDescriptor(
            address: new HotAddress($address),
            seedReference: $seedReference,
            derivationPath: $derivationPath,
        );
    }
}
