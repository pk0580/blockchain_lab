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
 * Оркестратор для POST /api/v1/withdrawals. Шаги:
 *
 *  1. Идемпотентность: ищем по `Idempotency-Key`. Если запись уже существует,
 *     сверяем отпечаток (fingerprint) и либо возвращаем существующую запись, либо выбрасываем 409.
 *  2. Загружаем Chain, определяем горячий кошелек (config + SigningClient::deriveAddress).
 *  3. Запрашиваем Fee::Application::EstimateFeeAction — получаем снимок (snapshot) (без
 *     импорта Fee::Domain в этот модуль, см. fromPrimitives/toSnapshot).
 *  4. Для EVM аллоцируем nonce через NonceAllocator (для BTC — пропускаем).
 *  5. TxBuilder строит неподписанную транзакцию (Withdrawal::Domain::BuiltTransaction).
 *  6. Сохраняем Withdrawal со статусом Requested → markAsBuilt → save (COMMIT).
 *  7. SigningClient::signRawTx → markAsSigned → save (COMMIT).
 *  8. ChainAdapter::broadcast → markAsBroadcasted → save (COMMIT) → генерация событий.
 *
 * Каждый шаг выполняется в собственной транзакции на случай сбоя в промежутке, чтобы
 * частичное состояние было доступно для polling-job'а Фазы 6.3. Любой сбой
 * приводит к withdrawal.fail() + 5xx с осмысленным сообщением.
 *
 * Прямых импортов Domain из других модулей нет: Fee передается через Application DTO,
 * Network — через общее ядро (Chain, Address, ChainAdapter, SigningClient).
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

        $existing = $this->repo->findByIdempotencyKey($data->idempotencyKey);
        if ($existing !== null) {
            $this->assertSameFingerprint($existing, $data);
            return new RequestWithdrawalResult($existing, reused: true);
        }

        if ($this->pauses->isPaused($data->chainId)) {
            throw ChainPausedException::forChain($data->chainId);
        }

        $chain = $this->chains->findById($data->chainId)
            ?? throw ChainNotFoundException::byId($data->chainId);

        $hot = $this->hotWallets->resolve($chain);

        $snapshot = $this->fees->handle(
            EstimateFeeData::fromPrimitives($data->chainId->value, $data->priority),
        )->toSnapshot();

        $feeQuote = new FeeQuoteSnapshot(
            priority: $snapshot->priority,
            breakdown: $snapshot->breakdown,
            estimatedAt: $snapshot->estimatedAt,
        );

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

    private function buildSignBroadcast(
        Withdrawal $withdrawal,
        Chain $chain,
        HotWalletDescriptor $hot,
        FeeQuoteSnapshot $feeQuote,
        ?NonceValue $nonce,
        DateTimeImmutable $now,
    ): void {
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
