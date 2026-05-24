<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Confirmation;

use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Domain\Exception\ConfirmationLookupFailedException;
use App\Modules\Withdrawal\Domain\ValueObject\ConfirmationObservation;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Bitcoin Core lookup: `getrawtransaction <txid> 1` (verbose JSON).
 *
 *  - `confirmations` ≥ 1  ⇒ {@see ConfirmationObservation::confirmed()}.
 *  - `confirmations` 0 / отсутствует ⇒ {@see ConfirmationObservation::pending()}
 *    (висит в мемпуле либо в блоке, ещё не считающемся канонически записанным).
 *  - RPC error -5 («No such mempool or blockchain transaction») ⇒ {@see ConfirmationObservation::dropped()}.
 *
 * Любой другой сбой = транспортный/протокольный, поднимаем
 * {@see ConfirmationLookupFailedException}, чтобы polling-job пометил запись
 * как «опросим позже».
 */
final readonly class BitcoinWithdrawalConfirmationLookup implements WithdrawalConfirmationLookup
{
    public function __construct(
        /** @var Closure(Chain): BitcoinRpcClient */
        private Closure $rpcFactory,
    ) {}

    public function observe(Chain $chain, TxHash $txHash): ConfirmationObservation
    {
        if ($chain->family !== ChainFamily::Bitcoin) {
            throw new RuntimeException(
                "BitcoinWithdrawalConfirmationLookup cannot serve family '{$chain->family->value}'."
            );
        }

        $rpc = ($this->rpcFactory)($chain);

        try {
            /** @var array<string, mixed>|string $raw */
            $raw = $rpc->call('getrawtransaction', [$txHash->value, 1]);
        } catch (BlockSourceException $e) {
            // bitcoind: «No such mempool or blockchain transaction» = код -5.
            // BlockSourceException::protocol сохраняет код в текстовом виде.
            if (str_contains($e->getMessage(), '-5')) {
                return ConfirmationObservation::dropped();
            }
            throw ConfirmationLookupFailedException::for($chain->id, $txHash, $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw ConfirmationLookupFailedException::for($chain->id, $txHash, $e->getMessage(), $e);
        }

        if (! is_array($raw)) {
            // verbose=1 должен возвращать объект; строка возможна только при verbose=0.
            throw ConfirmationLookupFailedException::for(
                $chain->id,
                $txHash,
                'getrawtransaction returned non-object result',
            );
        }

        $confirmations = isset($raw['confirmations']) ? (int) $raw['confirmations'] : 0;
        return $confirmations >= 1
            ? ConfirmationObservation::confirmed($confirmations)
            : ConfirmationObservation::pending();
    }
}
