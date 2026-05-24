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
 * Сборщик BTC транзакций (Greedy-largest-first). MVP Фазы 6.2:
 *
 *  1. `listunspent 1 9999 [hot_address]` → доступные UTXO.
 *  2. Сортируем по сумме (amount) по убыванию, выбираем необходимое количество,
 *     считаем сдачу = sum_in - amount - fee.
 *  3. `createrawtransaction` ⇒ unsigned hex.
 *  4. Передаем в signing-svc вместе с информацией о входах (prev-outs) для подписи.
 *
 * Комиссия оценивается как `satPerVbyte * estimated_vsize`. estimated_vsize
 * считается по эвристике: 110 байт на вход + 34 на выход + 10 на заголовок —
 * этого достаточно для regtest p2wpkh. Для серьезных сумм в mainnet
 * используется PSBT с estimatesmartfee + точный sign-test, что появится позже.
 *
 * BitcoinRpcClient берется для каждой сети через factory, чтобы поддерживать
 * несколько BTC-сетей (regtest/testnet/...) без перерегистрации.
 */
final readonly class BitcoinTxBuilder implements TxBuilder
{
    private const ESTIMATED_INPUT_VBYTES = 110;
    private const ESTIMATED_OUTPUT_VBYTES = 34;
    private const ESTIMATED_OVERHEAD_VBYTES = 10;
    private const SATOSHIS_PER_BTC = 100_000_000;

    /** BIP-125 «replace-by-fee»: sequence < 0xfffffffe = "replaceable". */
    private const RBF_SEQUENCE = 0xfffffffd;

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
        if ($chain->family !== ChainFamily::Bitcoin) {
            throw new RuntimeException(
                "BitcoinTxBuilder не может строить транзакции для семейства '{$chain->family->value}'."
            );
        }
        $satPerVbyte = (int) ($fee->breakdown['sat_per_vbyte'] ?? 0);
        if ($satPerVbyte < 1) {
            throw new RuntimeException(
                "BitcoinTxBuilder требует sat_per_vbyte >= 1 в снимке комиссии."
            );
        }

        $rpc = ($this->rpcFactory)($chain);

        try {
            /** @var list<array<string, mixed>> $utxos */
            $utxos = (array) $rpc->call('listunspent', [1, 9999, [$from->value]]);
        } catch (BlockSourceException $e) {
            throw new RuntimeException("Ошибка listunspent: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException("Ошибка listunspent: {$e->getMessage()}", 0, $e);
        }

        usort($utxos, function (array $a, array $b): int {
            $av = (float) ($a['amount'] ?? 0);
            $bv = (float) ($b['amount'] ?? 0);
            return $bv <=> $av;
        });

        $amountSat = (int) $amount->value;
        $sumSat = 0;
        $inputs = [];
        $outputCount = 2; // получатель + сдача; уточним при отсутствии сдачи

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

            $estimatedVsize = self::ESTIMATED_OVERHEAD_VBYTES
                + count($inputs) * self::ESTIMATED_INPUT_VBYTES
                + $outputCount * self::ESTIMATED_OUTPUT_VBYTES;
            $feeSat = $estimatedVsize * $satPerVbyte;

            if ($sumSat >= $amountSat + $feeSat) {
                break;
            }
        }

        if ($inputs === []) {
            throw new RuntimeException(
                "Нет доступных UTXO для горячего адреса '{$from->value}' в сети '{$chain->id->value}'."
            );
        }

        $estimatedVsize = self::ESTIMATED_OVERHEAD_VBYTES
            + count($inputs) * self::ESTIMATED_INPUT_VBYTES
            + $outputCount * self::ESTIMATED_OUTPUT_VBYTES;
        $feeSat = $estimatedVsize * $satPerVbyte;

        if ($sumSat < $amountSat + $feeSat) {
            throw new RuntimeException(
                "Недостаточно средств для вывода в '{$chain->id->value}': "
                ."есть {$sumSat} sat, требуется ".($amountSat + $feeSat)." sat."
            );
        }

        $changeSat = $sumSat - $amountSat - $feeSat;
        // Если сдача меньше пылевого порога (dust threshold, 546 sat) — добавляем её в комиссию.
        if ($changeSat > 0 && $changeSat < 546) {
            $changeSat = 0;
            $outputCount = 1;
        }

        $outputs = [
            $to->value => $this->satToBtcString($amountSat),
        ];
        if ($changeSat > 0) {
            $outputs[$from->value] = $this->satToBtcString($changeSat);
        }

        try {
            $rawHex = $rpc->call('createrawtransaction', [
                array_map(static fn (array $i): array => ['txid' => $i['txid'], 'vout' => $i['vout']], $inputs),
                $outputs,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Ошибка createrawtransaction: {$e->getMessage()}", 0, $e);
        }

        if (! is_string($rawHex) || $rawHex === '') {
            throw new RuntimeException('createrawtransaction вернул пустой результат.');
        }

        return new BuiltTransaction(
            rawHex: $rawHex,
            signingExtras: ['inputs' => $inputs],
        );
    }

    public function rebuild(
        Chain $chain,
        HotAddress $from,
        Address $to,
        WithdrawalAmount $amount,
        FeeQuoteSnapshot $fee,
        ?NonceValue $previousNonce,
        ?array $previousExtras,
    ): BuiltTransaction {
        if ($chain->family !== ChainFamily::Bitcoin) {
            throw new RuntimeException(
                "BitcoinTxBuilder не может пересобирать транзакции для семейства '{$chain->family->value}'."
            );
        }
        $satPerVbyte = (int) ($fee->breakdown['sat_per_vbyte'] ?? 0);
        if ($satPerVbyte < 1) {
            throw new RuntimeException(
                'BitcoinTxBuilder требует sat_per_vbyte >= 1 в снимке комиссии для замены.'
            );
        }

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

        $outputCount = 2;
        $estimatedVsize = self::ESTIMATED_OVERHEAD_VBYTES
            + count($inputs) * self::ESTIMATED_INPUT_VBYTES
            + $outputCount * self::ESTIMATED_OUTPUT_VBYTES;
        $feeSat = $estimatedVsize * $satPerVbyte;

        if ($sumSat < $amountSat + $feeSat) {
            throw new RuntimeException(
                "Замена BIP-125 недостаточна для '{$chain->id->value}': "
                ."входы {$sumSat} sat не могут покрыть сумму {$amountSat} + комиссию {$feeSat}."
            );
        }

        $changeSat = $sumSat - $amountSat - $feeSat;
        if ($changeSat > 0 && $changeSat < 546) {
            $changeSat = 0;
            $outputCount = 1;
            $estimatedVsize = self::ESTIMATED_OVERHEAD_VBYTES
                + count($inputs) * self::ESTIMATED_INPUT_VBYTES
                + $outputCount * self::ESTIMATED_OUTPUT_VBYTES;
            $feeSat = $estimatedVsize * $satPerVbyte;
            // Пересчитываем необходимость сдачи после изменения vsize.
            $changeSat = $sumSat - $amountSat - $feeSat;
            if ($changeSat < 0) {
                throw new RuntimeException(
                    "Замена BIP-125 стала недостаточной после отбрасывания пылевой сдачи в '{$chain->id->value}'."
                );
            }
            if ($changeSat > 0 && $changeSat < 546) {
                $changeSat = 0;
            } elseif ($changeSat > 0) {
                $outputCount = 2;
            }
        }

        $outputs = [
            $to->value => $this->satToBtcString($amountSat),
        ];
        if ($changeSat > 0) {
            $outputs[$from->value] = $this->satToBtcString($changeSat);
        }

        $rpc = ($this->rpcFactory)($chain);
        try {
            $rawHex = $rpc->call('createrawtransaction', [
                array_map(static fn (array $i): array => [
                    'txid' => $i['txid'],
                    'vout' => $i['vout'],
                    'sequence' => self::RBF_SEQUENCE,
                ], $inputs),
                $outputs,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Ошибка RBF createrawtransaction: {$e->getMessage()}", 0, $e);
        }

        if (! is_string($rawHex) || $rawHex === '') {
            throw new RuntimeException('RBF createrawtransaction вернул пустой результат.');
        }

        return new BuiltTransaction(
            rawHex: $rawHex,
            signingExtras: ['inputs' => $inputs, 'replacement' => true],
        );
    }

    private function satToBtcString(int $sat): string
    {
        // bcmath, 8 знаков после точки. PHP-float-арифметика тут не подходит,
        // потому что 0.1 BTC = 10000000 satoshis, а float-ы теряют точность.
        return bcdiv((string) $sat, (string) self::SATOSHIS_PER_BTC, 8);
    }
}
