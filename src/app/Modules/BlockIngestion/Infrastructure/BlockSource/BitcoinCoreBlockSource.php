<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\BlockSource;

use App\Modules\BlockIngestion\Domain\Contract\BlockSource;
use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedBlock;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedOutput;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedTransaction;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;

/**
 * Источник блоков Bitcoin Core, работающий по принципу опроса. Bitcoin RPC возвращает суммы в BTC как
 * числа с плавающей точкой (floats) — мы конвертируем их в сатоши как строковые представления целых чисел
 * через bcmul, никогда не доверяя точности float.
 *
 * Выходы (outputs) сводятся к паре (адрес, сумма). Скрипты Bitcoin, которые создают
 * несколько адресов (редко; в основном устаревшие 1-из-1) или не создают адресов (OP_RETURN,
 * мультиподпись без разрешения адреса на данном узле), пропускаются.
 */
final readonly class BitcoinCoreBlockSource implements BlockSource
{
    public function __construct(
        private BitcoinRpcClient $rpc,
        private Chain $chain,
    ) {}

    public function currentHead(): BlockHeight
    {
        return new BlockHeight($this->rpc->getBlockCount());
    }

    public function fetchBlockAt(BlockHeight $height): FetchedBlock
    {
        $hash = $this->rpc->getBlockHash($height->value);
        $block = $this->rpc->getBlock($hash, 2);

        $rawTxs = $block['tx'] ?? null;
        if (! is_array($rawTxs)) {
            throw BlockSourceException::protocol("getblock({$hash}) has no tx[] array.");
        }

        $parentHashRaw = (string) ($block['previousblockhash'] ?? '');
        if ($parentHashRaw === '') {
            throw BlockSourceException::protocol(
                "Block at height {$height->value} has no previousblockhash; "
                .'Phase 4 does not ingest genesis.'
            );
        }

        $timestamp = (new DateTimeImmutable())->setTimestamp((int) ($block['time'] ?? 0));
        $currency = new Currency($this->chain->nativeCurrency->symbol);

        $txs = [];
        foreach ($rawTxs as $tx) {
            if (! is_array($tx)) {
                continue;
            }
            $txs[] = $this->parseTransaction($tx);
        }

        return new FetchedBlock(
            height: $height,
            hash: new BlockHash($hash),
            parentHash: new BlockHash($parentHashRaw),
            timestamp: $timestamp,
            currency: $currency,
            transactions: $txs,
        );
    }

    /**
     * @param array<string, mixed> $tx
     */
    private function parseTransaction(array $tx): FetchedTransaction
    {
        $txid = (string) ($tx['txid'] ?? '');
        if ($txid === '') {
            throw BlockSourceException::protocol('Bitcoin RPC tx has no txid.');
        }

        $outputs = [];
        $vout = $tx['vout'] ?? null;
        if (is_array($vout)) {
            foreach ($vout as $out) {
                if (! is_array($out)) {
                    continue;
                }
                $address = $this->extractAddress($out);
                if ($address === null) {
                    continue;
                }
                $valueBtc = $out['value'] ?? null;
                if (! is_numeric($valueBtc)) {
                    continue;
                }
                $outputs[] = new FetchedOutput(
                    toAddress: $address,
                    amount: new Amount($this->btcToSatoshi((string) $valueBtc)),
                );
            }
        }

        return new FetchedTransaction(
            txHash: new TxHash($txid),
            fromAddress: null,
            outputs: $outputs,
        );
    }

    /**
     * @param array<string, mixed> $vout
     */
    private function extractAddress(array $vout): ?string
    {
        $script = $vout['scriptPubKey'] ?? null;
        if (! is_array($script)) {
            return null;
        }
        // Современный Bitcoin Core (0.22+) возвращает один 'address'.
        if (isset($script['address']) && is_string($script['address']) && $script['address'] !== '') {
            return $script['address'];
        }
        // Старые версии возвращали 'addresses' в виде списка.
        if (isset($script['addresses']) && is_array($script['addresses'])) {
            $first = $script['addresses'][0] ?? null;
            if (is_string($first) && $first !== '') {
                return $first;
            }
        }
        return null;
    }

    /**
     * Строка BTC типа "0.50000000" → строка сатоши "50000000". Использует bcmath
     * во избежание дрейфа float; никогда не приводит к float.
     */
    private function btcToSatoshi(string $btc): string
    {
        if (! is_numeric($btc)) {
            throw BlockSourceException::protocol("Non-numeric BTC value: '{$btc}'.");
        }
        /** @var numeric-string $btcNumeric */
        $btcNumeric = $btc;
        $satoshi = bcmul($btcNumeric, '100000000', 0);
        // bcmul может вернуть "0" для "0" — удаляем ведущий знак на всякий случай.
        return ltrim($satoshi, '+') ?: '0';
    }
}
