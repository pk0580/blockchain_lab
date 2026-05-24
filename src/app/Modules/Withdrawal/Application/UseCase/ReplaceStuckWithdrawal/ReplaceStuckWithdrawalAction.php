<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal;

use App\Modules\Network\Domain\Contract\ChainAdapterRegistry;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Withdrawal\Application\Contract\HotWalletResolver;
use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use App\Modules\Withdrawal\Application\Contract\WithdrawalIdGenerator;
use App\Modules\Withdrawal\Domain\Contract\TxBuilderRegistry;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Exception\WithdrawalNotFoundException;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Заменяет «зависший» withdrawal:
 *
 *  - **BTC:** BIP-125 RBF. Те же UTXO, sequence < 0xfffffffe, fee × bump.
 *  - **EVM:** тот же nonce, fee × bump.
 *
 * Поведение:
 *  1. Загружаем оригинал по `originalWithdrawalId`. Если он уже не Stuck —
 *     no-op (idempotency: listener мог дважды дернуться).
 *  2. Считаем новые fee-поля = старые × `withdrawal.rbf.fee_multiplier_bps`/10000.
 *  3. TxBuilder::rebuild → SigningClient::signRawTx → ChainAdapter::broadcast.
 *  4. Создаём новый Withdrawal (`replacement_of = original`), сохраняем
 *     в трёх транзакциях (Built → Signed → Broadcasted) тем же приёмом,
 *     что и `RequestWithdrawalAction`.
 *  5. Оригинал помечаем `markAsReplaced(new_id)` + сохраняем.
 *
 * Идемпотентность на уровне БД: новый withdrawal получает
 * `idempotency_key = "rbf:{original_id}"` — UNIQUE constraint спасёт от
 * дублирования при повторной доставке `WithdrawalStuck`.
 */
final readonly class ReplaceStuckWithdrawalAction
{
    public function __construct(
        private ChainRepository $chains,
        private ChainAdapterRegistry $adapters,
        private WithdrawalRepository $repo,
        private TxBuilderRegistry $builders,
        private SigningClient $signing,
        private HotWalletResolver $hotWallets,
        private WithdrawalIdGenerator $ids,
        private WithdrawalEventDispatcher $events,
        private DatabaseManager $db,
        private ConfigRepository $config,
    ) {}

    public function handle(ReplaceStuckWithdrawalData $data): ?ReplaceStuckWithdrawalResult
    {
        $originalId = new WithdrawalId($data->originalWithdrawalId);
        $original = $this->repo->findById($originalId)
            ?? throw WithdrawalNotFoundException::byId($originalId);

        // Idempotent re-entry: уже replaced (или не stuck) → выходим.
        if ($original->status() !== WithdrawalStatus::Stuck) {
            return null;
        }

        $rbfKey = new IdempotencyKey('rbf:'.$original->id->value);
        $existing = $this->repo->findByIdempotencyKey($rbfKey);
        if ($existing !== null) {
            // Параллельная замена уже создала row — listener не должен городить дубликат.
            return null;
        }

        $chain = $this->chains->findById($original->chainId)
            ?? throw ChainNotFoundException::byId($original->chainId);

        $hot = $this->hotWallets->resolve($chain);

        $bumpedFee = $this->bumpFee($original->feeQuote, $chain->family);

        $now = new DateTimeImmutable();
        $replacement = Withdrawal::request(
            id: $this->ids->next(),
            walletId: $original->walletId,
            chainId: $original->chainId,
            hotAddress: $original->hotAddress,
            toAddress: $original->toAddress,
            amount: $original->amount,
            currency: $original->currency,
            feeQuote: $bumpedFee,
            idempotencyKey: $rbfKey,
            now: $now,
            replacementOf: $original->id,
        );

        $builder = $this->builders->for($chain->family);
        $built = $builder->rebuild(
            chain: $chain,
            from: $hot->address,
            to: $original->toAddress,
            amount: $original->amount,
            fee: $bumpedFee,
            previousNonce: $original->nonce(),
            previousExtras: $original->signingExtras(),
        );

        $this->db->transaction(function () use ($replacement, $built, $original, $now): void {
            $replacement->markAsBuilt($built->rawHex, $original->nonce(), $built->signingExtras, $now);
            $this->repo->save($replacement);
        });

        $signed = $this->signing->signRawTx(
            family: $chain->family,
            seedReference: $hot->seedReference,
            path: $hot->derivationPath,
            rawHex: $built->rawHex,
            extra: $built->signingExtras,
        );

        $this->db->transaction(function () use ($replacement, $signed, $now): void {
            $replacement->markAsSigned($signed->hex, $now);
            $this->repo->save($replacement);
        });

        $adapter = $this->adapters->adapterFor($chain->id);
        $txHash = $adapter->broadcast(new SignedRawTx($chain->family, $signed->hex));

        $events = [];
        $this->db->transaction(function () use ($replacement, $original, $txHash, $now, &$events): void {
            $replacement->markAsBroadcasted($txHash, $now);
            $this->repo->save($replacement);

            // refetch оригинал в той же транзакции, чтобы быть уверенными в текущем статусе.
            $current = $this->repo->findById($original->id)
                ?? throw WithdrawalNotFoundException::byId($original->id);
            if ($current->status() === WithdrawalStatus::Stuck) {
                $current->markAsReplaced($replacement->id, $now);
                $this->repo->save($current);
                $events = array_merge(
                    $replacement->pullPendingEvents(),
                    $current->pullPendingEvents(),
                );
            } else {
                $events = $replacement->pullPendingEvents();
            }
        });

        foreach ($events as $event) {
            $this->events->dispatch($event);
        }

        return new ReplaceStuckWithdrawalResult(
            originalWithdrawalId: $original->id->value,
            replacementWithdrawalId: $replacement->id->value,
            replacementTxHash: $txHash->value,
        );
    }

    private function bumpFee(FeeQuoteSnapshot $original, ChainFamily $family): FeeQuoteSnapshot
    {
        $bps = (int) $this->config->get('withdrawal.rbf.fee_multiplier_bps', 12500);
        if ($bps <= 10000) {
            throw new RuntimeException(
                "RBF fee multiplier must be > 10000 bps (got {$bps}); replacement requires higher fee than original."
            );
        }
        $breakdown = $original->breakdown;

        if ($family === ChainFamily::Bitcoin) {
            $previous = (int) ($breakdown['sat_per_vbyte'] ?? 0);
            $bumped = max($previous + 1, (int) (($previous * $bps) / 10000));
            $breakdown['sat_per_vbyte'] = $bumped;
        } elseif ($family === ChainFamily::Evm) {
            $breakdown['max_fee_per_gas_wei'] = $this->mulBps(
                (string) ($breakdown['max_fee_per_gas_wei'] ?? '0'),
                $bps,
            );
            $breakdown['max_priority_fee_per_gas_wei'] = $this->mulBps(
                (string) ($breakdown['max_priority_fee_per_gas_wei'] ?? '0'),
                $bps,
            );
        } else {
            throw new RuntimeException(
                "Replacement fee bump not implemented for family '{$family->value}'."
            );
        }

        return new FeeQuoteSnapshot(
            priority: $original->priority,
            breakdown: $breakdown,
            estimatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Умножает целочисленный wei-string на bps/10000 без потери точности.
     * Принимает любую строку; нечисловые значения нормализуются в '0', чтобы
     * сломанный snapshot не уронил RBF на ровном месте.
     */
    private function mulBps(string $value, int $bps): string
    {
        $clean = preg_match('/^[0-9]+$/', $value) === 1 ? $value : '0';
        /** @var numeric-string $clean */
        $product = bcmul($clean, (string) $bps, 0);
        return bcdiv($product, '10000', 0);
    }
}
