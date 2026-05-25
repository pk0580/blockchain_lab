<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal;

use App\Modules\Fee\Application\UseCase\EstimateFee\EstimateFeeAction;
use App\Modules\Fee\Application\UseCase\EstimateFee\EstimateFeeData;
use App\Modules\Network\Domain\Contract\ChainAdapterRegistry;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Withdrawal\Application\Contract\HotWalletResolver;
use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use App\Modules\Withdrawal\Application\Contract\WithdrawalIdGenerator;
use App\Modules\Withdrawal\Domain\Contract\ChainPauseRegistry;
use App\Modules\Withdrawal\Domain\Contract\NonceAllocator;
use App\Modules\Withdrawal\Domain\Contract\TxBuilderRegistry;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Exception\ChainPausedException;
use App\Modules\Withdrawal\Domain\Exception\IdempotencyConflictException;
use App\Modules\Withdrawal\Domain\Exception\WithdrawalBroadcastFailedException;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Оркестратор `POST /api/v1/withdrawals`.
 *
 * Полный алгоритм — GUIDE.md, Урок 10 «Оркестрация: RequestWithdrawalAction».
 *
 * Шаги:
 *  1. Идемпотентность по `Idempotency-Key`:
 *     - запись существует и fingerprint совпал  → вернуть существующую (reused=true);
 *     - запись существует и fingerprint другой  → 409 IdempotencyConflict.
 *  2. ChainPauseRegistry::isPaused — иначе ChainPausedException → HTTP 503 (Урок 11).
 *  3. Загрузить Chain, определить горячий кошелёк (HotWalletResolver).
 *  4. Fee::EstimateFeeAction → FeeQuoteSnapshot в withdrawal (Урок 9).
 *  5. Для EVM — выделить nonce (NonceAllocator). Для BTC — пропустить (нет nonce).
 *  6. Withdrawal::request — статус Requested.
 *  7. Build → Sign → Broadcast в ТРЁХ отдельных транзакциях:
 *     - TxBuilder::build()  → COMMIT (markAsBuilt).
 *     - SigningClient::signRawTx() → COMMIT (markAsSigned).
 *     - ChainAdapter::broadcast() → COMMIT (markAsBroadcasted) + диспатч событий.
 *
 * ⚠️ Почему три транзакции, а не одна (GUIDE §10, конец «Оркестрация»):
 * если упасть посередине, состояние всё равно в БД. Polling-задача увидит
 * «есть Built, но не Signed» → продолжит со следующего шага. Withdrawal —
 * возобновляемый процесс. Альтернатива «всё в одной транзакции» оставила бы
 * нас с потерянными подписями и неотправленными байтами — а это деньги.
 *
 * Любая ошибка вызывает {@see failQuietly()} → клиент получает 5xx.
 * Идемпотентность защищена на трёх уровнях (GUIDE §10):
 *   1) HTTP middleware (Modules/Idempotency, Урок 12);
 *   2) локальный findByIdempotencyKey + UNIQUE в `withdrawals`;
 *   3) идемпотентность по `replacementOf` для RBF (Урок 11).
 *
 * Импортов Domain из других модулей нет: Fee передаётся через Application DTO,
 * Network — через общее ядро (Chain, Address, ChainAdapter, SigningClient).
 *
 * @see \GUIDE.md  Урок 10 (#урок-10--вывод-средств-withdrawal)
 */
final readonly class RequestWithdrawalAction
{
    public function __construct(
        private ChainRepository $chains,
        private ChainAdapterRegistry $adapters,
        private WithdrawalRepository $repo,
        private EstimateFeeAction $fees,
        private NonceAllocator $nonces,
        private TxBuilderRegistry $builders,
        private SigningClient $signing,
        private HotWalletResolver $hotWallets,
        private WithdrawalIdGenerator $ids,
        private WithdrawalEventDispatcher $events,
        private ChainPauseRegistry $pauses,
        private DatabaseManager $db,
        private ?DateTimeImmutable $clock = null,
    ) {}

    public function handle(RequestWithdrawalData $data): RequestWithdrawalResult
    {
        $now = $this->clock ?? new DateTimeImmutable();

        // Шаг 1 (GUIDE §10): идемпотентность.
        // Defence-in-depth — на случай, если HTTP-middleware не отработал.
        $existing = $this->repo->findByIdempotencyKey($data->idempotencyKey);
        if ($existing !== null) {
            $this->assertSameFingerprint($existing, $data);
            return new RequestWithdrawalResult($existing, reused: true);
        }

        // Шаг 2 (GUIDE §10 + §11): пауза сети (после слишком глубокого reorg).
        if ($this->pauses->isPaused($data->chainId)) {
            throw ChainPausedException::forChain($data->chainId);
        }

        // Шаг 3 (GUIDE §10): сеть + горячий кошелёк (адрес-источник).
        $chain = $this->chains->findById($data->chainId)
            ?? throw ChainNotFoundException::byId($data->chainId);

        $hot = $this->hotWallets->resolve($chain);

        // Шаг 4 (GUIDE §9, §10): оценка комиссии. Снимок (FeeQuoteSnapshot)
        // кладётся в withdrawal — он зафиксирован на момент request, не пересчитывается.
        $snapshot = $this->fees->handle(
            EstimateFeeData::fromPrimitives($data->chainId->value, $data->priority),
        )->toSnapshot();

        $feeQuote = new FeeQuoteSnapshot(
            priority: $snapshot->priority,
            breakdown: $snapshot->breakdown,
            estimatedAt: $snapshot->estimatedAt,
        );

        // Шаг 5 (GUIDE §10): nonce — только для EVM (Bitcoin nonce не использует, см. Урок 3).
        $nonce = $chain->family === ChainFamily::Evm
            ? $this->nonces->allocate($chain, $hot->address)
            : null;

        $withdrawal = Withdrawal::request(
            id: $this->ids->next(),
            walletId: $data->walletId,
            chainId: $data->chainId,
            hotAddress: $hot->address,
            toAddress: $data->toAddress,
            amount: $data->amount,
            currency: $data->currency,
            feeQuote: $feeQuote,
            idempotencyKey: $data->idempotencyKey,
            now: $now,
        );

        try {
            $this->buildSignBroadcast($withdrawal, $chain, $hot, $feeQuote, $nonce, $now);
        } catch (Throwable $e) {
            $this->failQuietly($withdrawal, $e, $now);
            throw WithdrawalBroadcastFailedException::from($withdrawal->id, $e->getMessage(), $e);
        }

        return new RequestWithdrawalResult($withdrawal, reused: false);
    }

    /**
     * Шаг 7 GUIDE §10: build → sign → broadcast в трёх отдельных транзакциях,
     * чтобы withdrawal был возобновляемым процессом (см. docblock класса).
     */
    private function buildSignBroadcast(
        Withdrawal $withdrawal,
        Chain $chain,
        HotWalletDescriptor $hot,
        FeeQuoteSnapshot $feeQuote,
        ?NonceValue $nonce,
        DateTimeImmutable $now,
    ): void {
        // 7a: Build — TxBuilder собирает unsigned rawHex + signingExtras.
        $builder = $this->builders->for($chain->family);
        $built = $builder->build(
            chain: $chain,
            from: $hot->address,
            to: $withdrawal->toAddress,
            amount: $withdrawal->amount,
            fee: $feeQuote,
            nonce: $nonce,
        );

        $this->db->transaction(function () use ($withdrawal, $built, $nonce, $now): void {
            $withdrawal->markAsBuilt($built->rawHex, $nonce, $built->signingExtras, $now);
            $this->repo->save($withdrawal);
        });

        // 7b: Sign — приватный ключ остаётся в signing-svc (GUIDE §2).
        $signed = $this->signing->signRawTx(
            family: $chain->family,
            seedReference: $hot->seedReference,
            path: $hot->derivationPath,
            rawHex: $built->rawHex,
            extra: $built->signingExtras,
        );

        $this->db->transaction(function () use ($withdrawal, $signed, $now): void {
            $withdrawal->markAsSigned($signed->hex, $now);
            $this->repo->save($withdrawal);
        });

        // 7c: Broadcast — отправляем подписанную tx в mempool сети.
        $adapter = $this->adapters->adapterFor($chain->id);
        $txHash = $adapter->broadcast(new SignedRawTx($chain->family, $signed->hex));

        $events = [];
        $this->db->transaction(function () use ($withdrawal, $txHash, $now, &$events): void {
            $withdrawal->markAsBroadcasted($txHash, $now);
            $this->repo->save($withdrawal);
            $events = $withdrawal->pullPendingEvents();
        });

        foreach ($events as $event) {
            $this->events->dispatch($event);
        }
    }

    private function failQuietly(Withdrawal $withdrawal, Throwable $e, DateTimeImmutable $now): void
    {
        try {
            $this->db->transaction(function () use ($withdrawal, $e, $now): void {
                if ($withdrawal->status()->isTerminal()) {
                    return;
                }
                $withdrawal->fail($e->getMessage(), $now);
                $this->repo->save($withdrawal);
            });
        } catch (Throwable) {
            // Если уже Failed (повторный fail()) — невозможный переход, гасим тихо.
        }
    }

    private function assertSameFingerprint(Withdrawal $existing, RequestWithdrawalData $data): void
    {
        $previous = [
            'wallet_id' => $existing->walletId->value,
            'chain_id' => $existing->chainId->value,
            'to_address' => $existing->toAddress->value,
            'amount' => $existing->amount->value,
            'currency' => $existing->currency->value,
            'priority' => $existing->feeQuote->priority,
        ];
        if ($previous !== $data->fingerprint()) {
            throw IdempotencyConflictException::payloadMismatch($data->idempotencyKey);
        }
    }
}
