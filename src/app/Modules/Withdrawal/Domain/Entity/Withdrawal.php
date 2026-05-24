<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Entity;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Event\WithdrawalBroadcasted;
use App\Modules\Withdrawal\Domain\Event\WithdrawalBuilt;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirmed;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirming;
use App\Modules\Withdrawal\Domain\Event\WithdrawalFailed;
use App\Modules\Withdrawal\Domain\Event\WithdrawalReplaced;
use App\Modules\Withdrawal\Domain\Event\WithdrawalRequested;
use App\Modules\Withdrawal\Domain\Event\WithdrawalSigned;
use App\Modules\Withdrawal\Domain\Event\WithdrawalStuck;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DateTimeImmutable;

/**
 * Withdrawal aggregate. Все мутирующие методы — целевые операции (markAsBuilt,
 * markAsSigned, markAsBroadcasted, markAsConfirming/Confirmed/Stuck/Replaced, fail),
 * а не сеттеры. Каждый шаг продвигает статус через
 * {@see WithdrawalStatus::assertCanTransitionTo()} и поднимает domain event,
 * который Application::Action пуляет через диспатчер.
 *
 * Domain-сторона осознанно ничего не знает про PG / Eloquent / HTTP — здесь
 * только бизнес-инварианты state machine.
 */
final class Withdrawal
{
    /** @var list<object> */
    private array $pendingEvents = [];

    /**
     * @param array<string, mixed>|null $signingExtras
     */
    private function __construct(
        public readonly WithdrawalId $id,
        public readonly WalletId $walletId,
        public readonly ChainId $chainId,
        public readonly HotAddress $hotAddress,
        public readonly Address $toAddress,
        public readonly WithdrawalAmount $amount,
        public readonly Currency $currency,
        public readonly FeeQuoteSnapshot $feeQuote,
        public readonly IdempotencyKey $idempotencyKey,
        public readonly DateTimeImmutable $requestedAt,
        private WithdrawalStatus $status,
        private ?NonceValue $nonce,
        private ?string $rawTxHex,
        private ?array $signingExtras,
        private ?TxHash $txHash,
        private ?DateTimeImmutable $broadcastAt,
        private ?DateTimeImmutable $confirmedAt,
        private int $confirmations,
        private ?WithdrawalId $replacementOf,
        private ?string $failureReason,
        private int $version,
    ) {}

    public static function request(
        WithdrawalId $id,
        WalletId $walletId,
        ChainId $chainId,
        HotAddress $hotAddress,
        Address $toAddress,
        WithdrawalAmount $amount,
        Currency $currency,
        FeeQuoteSnapshot $feeQuote,
        IdempotencyKey $idempotencyKey,
        DateTimeImmutable $now,
        ?WithdrawalId $replacementOf = null,
    ): self {
        $self = new self(
            id: $id,
            walletId: $walletId,
            chainId: $chainId,
            hotAddress: $hotAddress,
            toAddress: $toAddress,
            amount: $amount,
            currency: $currency,
            feeQuote: $feeQuote,
            idempotencyKey: $idempotencyKey,
            requestedAt: $now,
            status: WithdrawalStatus::Requested,
            nonce: null,
            rawTxHex: null,
            signingExtras: null,
            txHash: null,
            broadcastAt: null,
            confirmedAt: null,
            confirmations: 0,
            replacementOf: $replacementOf,
            failureReason: null,
            version: 0,
        );
        $self->pendingEvents[] = new WithdrawalRequested($id, $chainId, $walletId, $now);
        return $self;
    }

    /**
     * Replay для репозитория. Никаких событий не поднимает.
     *
     * @param array<string, mixed>|null $signingExtras
     */
    public static function reconstitute(
        WithdrawalId $id,
        WalletId $walletId,
        ChainId $chainId,
        HotAddress $hotAddress,
        Address $toAddress,
        WithdrawalAmount $amount,
        Currency $currency,
        FeeQuoteSnapshot $feeQuote,
        IdempotencyKey $idempotencyKey,
        DateTimeImmutable $requestedAt,
        WithdrawalStatus $status,
        ?NonceValue $nonce,
        ?string $rawTxHex,
        ?array $signingExtras,
        ?TxHash $txHash,
        ?DateTimeImmutable $broadcastAt,
        ?DateTimeImmutable $confirmedAt,
        int $confirmations,
        ?WithdrawalId $replacementOf,
        ?string $failureReason,
        int $version,
    ): self {
        return new self(
            id: $id,
            walletId: $walletId,
            chainId: $chainId,
            hotAddress: $hotAddress,
            toAddress: $toAddress,
            amount: $amount,
            currency: $currency,
            feeQuote: $feeQuote,
            idempotencyKey: $idempotencyKey,
            requestedAt: $requestedAt,
            status: $status,
            nonce: $nonce,
            rawTxHex: $rawTxHex,
            signingExtras: $signingExtras,
            txHash: $txHash,
            broadcastAt: $broadcastAt,
            confirmedAt: $confirmedAt,
            confirmations: $confirmations,
            replacementOf: $replacementOf,
            failureReason: $failureReason,
            version: $version,
        );
    }

    /**
     * @param array<string, mixed>|null $signingExtras сборочные метаданные, нужные
     *        для RBF: для BTC — список inputs, для EVM — chain_id/nonce/поля комиссии.
     */
    public function markAsBuilt(string $rawTxHex, ?NonceValue $nonce, ?array $signingExtras, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WithdrawalStatus::Built);
        if ($rawTxHex === '') {
            throw new \InvalidArgumentException('rawTxHex не может быть пустым при пометке как собранного.');
        }
        $this->status = WithdrawalStatus::Built;
        $this->rawTxHex = $rawTxHex;
        $this->signingExtras = $signingExtras;
        if ($nonce !== null) {
            $this->nonce = $nonce;
        }
        $this->pendingEvents[] = new WithdrawalBuilt($this->id, $now);
    }

    public function markAsSigned(string $signedHex, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WithdrawalStatus::Signed);
        if ($signedHex === '') {
            throw new \InvalidArgumentException('signedHex не может быть пустым при пометке как подписанного.');
        }
        $this->status = WithdrawalStatus::Signed;
        $this->rawTxHex = $signedHex;
        $this->pendingEvents[] = new WithdrawalSigned($this->id, $now);
    }

    public function markAsBroadcasted(TxHash $txHash, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WithdrawalStatus::Broadcasted);
        $this->status = WithdrawalStatus::Broadcasted;
        $this->txHash = $txHash;
        $this->broadcastAt = $now;
        $this->pendingEvents[] = new WithdrawalBroadcasted($this->id, $txHash, $now);
    }

    /**
     * Фаза 6.3: фоновая задача (polling job) фиксирует промежуточные подтверждения. Метод
     * идемпотентен — при том же значении $confirmations события не создаются,
     * в этом случае ничего не делаем.
     */
    public function markAsConfirming(int $confirmations, DateTimeImmutable $now): void
    {
        if ($confirmations < 1) {
            throw new \InvalidArgumentException(
                "Для статуса Confirming требуется как минимум 1 подтверждение, получено {$confirmations}."
            );
        }
        if ($this->status === WithdrawalStatus::Confirming && $confirmations === $this->confirmations) {
            return;
        }
        $this->status->assertCanTransitionTo(WithdrawalStatus::Confirming);
        $this->status = WithdrawalStatus::Confirming;
        $this->confirmations = $confirmations;
        $this->pendingEvents[] = new WithdrawalConfirming($this->id, $confirmations, $now);
    }

    public function markAsConfirmed(int $confirmations, DateTimeImmutable $now): void
    {
        if ($confirmations < 1) {
            throw new \InvalidArgumentException(
                "Для статуса Confirmed требуется как минимум 1 подтверждение, получено {$confirmations}."
            );
        }
        $this->status->assertCanTransitionTo(WithdrawalStatus::Confirmed);
        $this->status = WithdrawalStatus::Confirmed;
        $this->confirmations = $confirmations;
        $this->confirmedAt = $now;
        $this->pendingEvents[] = new WithdrawalConfirmed($this->id, $confirmations, $now);
    }

    public function markAsStuck(DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WithdrawalStatus::Stuck);
        $this->status = WithdrawalStatus::Stuck;
        $this->pendingEvents[] = new WithdrawalStuck($this->id, $this->chainId, $now);
    }

    public function markAsReplaced(WithdrawalId $replacementId, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WithdrawalStatus::Replaced);
        $this->status = WithdrawalStatus::Replaced;
        $this->pendingEvents[] = new WithdrawalReplaced($this->id, $replacementId, $now);
    }

    public function fail(string $reason, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WithdrawalStatus::Failed);
        $this->status = WithdrawalStatus::Failed;
        $this->failureReason = $reason;
        $this->pendingEvents[] = new WithdrawalFailed($this->id, $reason, $now);
    }

    public function status(): WithdrawalStatus
    {
        return $this->status;
    }

    public function nonce(): ?NonceValue
    {
        return $this->nonce;
    }

    public function rawTxHex(): ?string
    {
        return $this->rawTxHex;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function signingExtras(): ?array
    {
        return $this->signingExtras;
    }

    public function txHash(): ?TxHash
    {
        return $this->txHash;
    }

    public function broadcastAt(): ?DateTimeImmutable
    {
        return $this->broadcastAt;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function confirmations(): int
    {
        return $this->confirmations;
    }

    public function replacementOf(): ?WithdrawalId
    {
        return $this->replacementOf;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return list<object>
     */
    public function pullPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }
}
