<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\TxBuilder;

use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Withdrawal\Domain\Contract\TxBuilder;
use App\Modules\Withdrawal\Domain\ValueObject\BuiltTransaction;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Сборщик BTC-транзакций по стратегии «Greedy-largest-first».
 *
 * Алгоритм (GUIDE.md, Урок 3 «UTXO-модель», раздел «В коде»):
 *
 *  1. `listunspent 1 9999 [hot_address]` → список доступных UTXO горячего кошелька.
 *  2. Сортируем UTXO по сумме (amount) по убыванию, набираем сверху, пока
 *     `Σinputs ≥ amount + fee`.
 *  3. Сдача = `Σinputs − amount − fee`. Если меньше dust threshold
 *     (546 сатоши) — «съедается» комиссией.
 *  4. `createrawtransaction` ⇒ unsigned hex.
 *  5. Передаём в signing-svc вместе с prev-outs (нужны подписанту для P2WPKH SIGHASH).
 *
 * Комиссия = `satPerVbyte * estimated_vsize`. estimated_vsize считается по
 * эвристике: 110 байт на вход + 34 на выход + 10 на заголовок — этого
 * достаточно для regtest p2wpkh. Подробнее про sat/vbyte — GUIDE.md, Урок 9.
 *
 * Метод {@see rebuild()} реализует BIP-125 RBF — см. GUIDE.md, Урок 11.
 *
 * ⚠️ Все суммы — целые числа в сатоши (см. GUIDE §3, врезка про единицы).
 * Конвертация sat ↔ BTC идёт через bcmath, потому что float-арифметика
 * на 8 знаков после точки уже теряет точность для больших сумм.
 *
 * BitcoinRpcClient берётся для каждой сети через factory, чтобы поддерживать
 * несколько BTC-сетей (regtest/testnet/...) без перерегистрации.
 *
 * @see \GUIDE.md  Урок 3 (#урок-3--транзакция-utxo-против-аккаунта)
 * @see \GUIDE.md  Урок 9 (#урок-9--комиссия-fee)
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
 */
final readonly class BitcoinTxBuilder implements TxBuilder
{
    private const int ESTIMATED_INPUT_VBYTES = 110;
    private const int ESTIMATED_OUTPUT_VBYTES = 34;
    private const int ESTIMATED_OVERHEAD_VBYTES = 10;
    private const int SATOSHIS_PER_BTC = 100_000_000;

    /**
     * BIP-125 «opt-in replace-by-fee»: sequence < 0xfffffffe = «replaceable».
     * См. GUIDE.md, Урок 11 — раздел «BIP-125 в Bitcoin: подробнее».
     */
    private const int RBF_SEQUENCE = 0xfffffffd;

    public function __construct(
        /** @var Closure(Chain): BitcoinRpcClient */
        private Closure $rpcFactory,
    ) {}

    public function build(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $nonce,
    ): BuiltTransaction {
        $this->ensureBitcoinChain($chain);
        $satPerVbyte = $this->getSatPerVbyte($fee);

        $rpc = ($this->rpcFactory)($chain);
        $utxos = $this->fetchSortedUtxos($rpc, $from);

        $amountSat = (int) $amount->value;
        $selection = $this->selectUtxos($utxos, $amountSat, $satPerVbyte);

        $outputs = $this->prepareOutputs(
            $from->value,
            $to->value,
            $amountSat,
            $selection['sumSat'],
            $selection['feeSat']
        );

        $rawHex = $this->createRawTx($rpc, $selection['inputs'], $outputs);

        return new BuiltTransaction(
            rawHex: $rawHex,
            signingExtras: ['inputs' => $selection['inputs']],
        );
    }

    /**
     * BIP-125 RBF — пересборка транзакции с повышенной комиссией (Replace-By-Fee).
     *
     * Условия Bitcoin для замены (GUIDE.md, Урок 11 — «BIP-125 в Bitcoin: подробнее»):
     *  - Все входы новой транзакции имеют sequence < 0xfffffffe (opt-in RBF).
     *  - Новая транзакция платит достаточно высокую дополнительную fee (не ниже min-relay).
     *  - Она тратит хотя бы один из тех же входов, что и старая.
     *
     * ⚠️ Поэтому здесь мы берём ИМЕННО ТЕ ЖЕ входы из `previousExtras['inputs']`
     * (а не делаем новый listunspent) и принудительно выставляем `sequence = 0xfffffffd`.
     */
    public function rebuild(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $previousNonce,
        ?array $previousExtras,
    ): BuiltTransaction {
        $this->ensureBitcoinChain($chain, true);
        $satPerVbyte = $this->getSatPerVbyte($fee);

        /** @var list<array<string, mixed>>|null $previousInputs */
        $previousInputs = $previousExtras['inputs'] ?? null;
        if (! is_array($previousInputs) || $previousInputs === []) {
            throw new RuntimeException(
                'BitcoinTxBuilder::rebuild требует входы предыдущей транзакции в signing_extras.'
            );
        }

        $amountSat = (int) $amount->value;
        $sumSat = 0;
        $inputs = [];
        foreach ($previousInputs as $utxo) {
            $utxoSat = (int) ($utxo['amount_sat'] ?? 0);
            $inputs[] = [
                'txid' => (string) ($utxo['txid'] ?? ''),
                'vout' => (int) ($utxo['vout'] ?? 0),
                'scriptPubKey' => (string) ($utxo['scriptPubKey'] ?? ''),
                'amount_sat' => $utxoSat,
            ];
            $sumSat += $utxoSat;
        }

        $estimatedVsize = $this->calculateVsize(count($inputs), 2);
        $feeSat = $estimatedVsize * $satPerVbyte;

        if ($sumSat < $amountSat + $feeSat) {
            // Если с 2 выходами не хватает, пробуем без сдачи (1 выход)
            $estimatedVsizeOneOutput = $this->calculateVsize(count($inputs), 1);
            $feeSatOneOutput = $estimatedVsizeOneOutput * $satPerVbyte;
            if ($sumSat < $amountSat + $feeSatOneOutput) {
                throw new RuntimeException(
                    "Замена BIP-125 недостаточна для '{$chain->id->value}': "
                    ."входы {$sumSat} sat не могут покрыть сумму {$amountSat} + комиссию {$feeSatOneOutput}."
                );
            }
        }

        $outputs = $this->prepareOutputs(
            $from->value,
            $to->value,
            $amountSat,
            $sumSat,
            $feeSat
        );

        $rpc = ($this->rpcFactory)($chain);
        $rawHex = $this->createRawTx($rpc, $inputs, $outputs, true);

        return new BuiltTransaction(
            rawHex: $rawHex,
            signingExtras: ['inputs' => $inputs, 'replacement' => true],
        );
    }

    private function ensureBitcoinChain(Chain $chain, bool $isRebuild = false): void
    {
        if ($chain->family !== ChainFamily::Bitcoin) {
            $action = $isRebuild ? 'пересобирать' : 'строить';
            throw new RuntimeException(
                "BitcoinTxBuilder не может {$action} транзакции для семейства '{$chain->family->value}'."
            );
        }
    }

    private function getSatPerVbyte(FeeQuoteSnapshot $fee): int
    {
        $satPerVbyte = (int) ($fee->breakdown['sat_per_vbyte'] ?? 0);
        if ($satPerVbyte < 1) {
            throw new RuntimeException(
                'BitcoinTxBuilder требует sat_per_vbyte >= 1 в снимке комиссии.'
            );
        }

        return $satPerVbyte;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSortedUtxos(BitcoinRpcClient $rpc, HotAddress $from): array
    {
        try {
            /** @var list<array<string, mixed>> $utxos */
            $utxos = (array) $rpc->call('listunspent', [1, 9999, [$from->value]]);
        } catch (Throwable $e) {
            throw new RuntimeException("Ошибка listunspent: {$e->getMessage()}", 0, $e);
        }

        usort($utxos, function (array $a, array $b): int {
            $av = (float) ($a['amount'] ?? 0);
            $bv = (float) ($b['amount'] ?? 0);
            return $bv <=> $av;
        });

        return $utxos;
    }

    /**
     * @param list<array<string, mixed>> $utxos
     * @return array{inputs: list<array<string, mixed>>, sumSat: int, feeSat: int}
     */
    private function selectUtxos(array $utxos, int $amountSat, int $satPerVbyte): array
    {
        $sumSat = 0;
        $inputs = [];
        $outputCount = 2;

        foreach ($utxos as $utxo) {
            $btc = (float) ($utxo['amount'] ?? 0);
            $utxoSat = (int) round($btc * self::SATOSHIS_PER_BTC);
            $inputs[] = [
                'txid' => (string) ($utxo['txid'] ?? ''),
                'vout' => (int) ($utxo['vout'] ?? 0),
                'scriptPubKey' => (string) ($utxo['scriptPubKey'] ?? ''),
                'amount_sat' => $utxoSat,
            ];
            $sumSat += $utxoSat;

            $feeSat = $this->calculateVsize(count($inputs), $outputCount) * $satPerVbyte;

            if ($sumSat >= $amountSat + $feeSat) {
                break;
            }
        }

        if ($inputs === []) {
            throw new RuntimeException('Нет доступных UTXO для горячего адреса.');
        }

        $feeSat = $this->calculateVsize(count($inputs), $outputCount) * $satPerVbyte;

        if ($sumSat < $amountSat + $feeSat) {
            throw new RuntimeException(
                "Недостаточно средств: есть {$sumSat} sat, требуется " . ($amountSat + $feeSat) . " sat."
            );
        }

        return [
            'inputs' => $inputs,
            'sumSat' => $sumSat,
            'feeSat' => $feeSat,
        ];
    }

    private function calculateVsize(int $inputCount, int $outputCount): int
    {
        return self::ESTIMATED_OVERHEAD_VBYTES
            + $inputCount * self::ESTIMATED_INPUT_VBYTES
            + $outputCount * self::ESTIMATED_OUTPUT_VBYTES;
    }

    /**
     * @return array<string, string>
     */
    private function prepareOutputs(
        string $fromAddress,
        string $toAddress,
        int $amountSat,
        int $sumSat,
        int $feeSat
    ): array {
        $changeSat = $sumSat - $amountSat - $feeSat;

        // Dust threshold = 546 сатоши
        if ($changeSat > 0 && $changeSat < 546) {
            $changeSat = 0;
        }

        $outputs = [
            $toAddress => $this->satToBtcString($amountSat),
        ];

        if ($changeSat > 0) {
            $outputs[$fromAddress] = $this->satToBtcString($changeSat);
        }

        return $outputs;
    }

    /**
     * @param list<array<string, mixed>> $inputs
     * @param array<string, string> $outputs
     */
    private function createRawTx(BitcoinRpcClient $rpc, array $inputs, array $outputs, bool $isRbf = false): string
    {
        $rpcInputs = array_map(static fn (array $i): array => [
            'txid' => $i['txid'],
            'vout' => $i['vout'],
            'sequence' => $isRbf ? self::RBF_SEQUENCE : 0xffffffff,
        ], $inputs);

        try {
            $rawHex = $rpc->call('createrawtransaction', [$rpcInputs, $outputs]);
        } catch (Throwable $e) {
            $prefix = $isRbf ? 'RBF ' : '';
            throw new RuntimeException("Ошибка {$prefix}createrawtransaction: {$e->getMessage()}", 0, $e);
        }

        if (! is_string($rawHex) || $rawHex === '') {
            throw new RuntimeException('createrawtransaction вернул пустой результат.');
        }

        return $rawHex;
    }

    private function satToBtcString(int $sat): string
    {
        // bcmath, 8 знаков после точки. PHP-float-арифметика тут не подходит,
        // потому что 0.1 BTC = 10000000 satoshis, а float-ы теряют точность.
        return bcdiv((string) $sat, (string) self::SATOSHIS_PER_BTC, 8);
    }
}
