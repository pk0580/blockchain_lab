<?php

declare(strict_types=1);

namespace App\Modules\Network\UI\Console;

use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainData;
use App\Modules\Network\Domain\Exception\ChainAlreadyRegisteredException;
use Illuminate\Console\Command;

final class RegisterChainCommand extends Command
{
    /** @var string */
    protected $signature = 'chain:register
        {id : Идентификатор в kebab-case, например, ethereum-sepolia}
        {--name= : Человекочитаемое имя}
        {--family= : bitcoin|evm|tron}
        {--currency= : Символ нативной валюты (например, ETH)}
        {--decimals=18 : Количество десятичных знаков валюты}
        {--confirmations=12 : Требуемое количество подтверждений}
        {--max-reorg=64 : Максимальная глубина реорга, которую нужно обрабатывать}
        {--rpc=* : URL-адреса RPC-эндпоинтов (повторяемый). По умолчанию kind=http; используйте префикс ws:// или wss:// для вебсокетов.}
        {--disable : Зарегистрировать, но оставить отключенным}
    ';

    /** @var string */
    protected $description = 'Регистрирует блокчейн-сеть в реестре платформы.';

    public function handle(RegisterChainAction $action): int
    {
        $endpoints = [];
        /** @var list<string> $rpcs */
        $rpcs = (array) $this->option('rpc');
        foreach ($rpcs as $url) {
            $endpoints[] = [
                'url' => $url,
                'kind' => str_starts_with($url, 'ws') ? 'ws' : 'http',
            ];
        }

        if ($endpoints === []) {
            $this->error('Требуется как минимум один --rpc=<url>.');
            return self::FAILURE;
        }

        $data = new RegisterChainData(
            chainId: (string) $this->argument('id'),
            name: (string) ($this->option('name') ?? $this->argument('id')),
            family: (string) $this->option('family'),
            currencySymbol: strtoupper((string) $this->option('currency')),
            currencyDecimals: (int) $this->option('decimals'),
            requiredConfirmations: (int) $this->option('confirmations'),
            maxReorgDepth: (int) $this->option('max-reorg'),
            endpoints: $endpoints,
            enable: ! $this->option('disable'),
        );

        try {
            $chainId = $action->handle($data);
        } catch (ChainAlreadyRegisteredException $e) {
            $this->warn($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Chain '{$chainId->value}' registered.");
        return self::SUCCESS;
    }
}
