<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Entity;

use App\Modules\BlockIngestion\Domain\Event\TransactionDetected;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Корень агрегата для одного платежа в блокчейне на один из наших отслеживаемых
 * адресов. Одна исходная транзакция, которая платит на N наших адресов, создает N
 * строк IncomingTransaction — уникальность определяется по (chainId, txHash, toAddress).
 *
 * Количество подтверждений и статус обновляются модулем Confirmation через
 * отдельный репозиторий, учитывающий агрегат, в Фазе 4. Сеттер здесь существует
 * для этого взаимодействия внутри границ; контроллеры не обращаются к нему напрямую.
 */
final class IncomingTransaction
{
    /** @var list<object> */
    private array $pendingEvents = [];

    private function __construct(
        public readonly IncomingTransactionId $id,
        public readonly ChainId $chainId,
        public readonly TxHash $txHash,
        public readonly ?BlockHeight $blockHeight,
        public readonly ?BlockHash $blockHash,
        public readonly ?string $fromAddress,
        public readonly string $toAddress,
        public readonly Amount $amount,
        public readonly Currency $currency,
        private IncomingTxStatus $status,
        private int $confirmations,
        public readonly DateTimeImmutable $detectedAt,
    ) {
        if ($toAddress === '' || mb_strlen($toAddress) > 96) {
            throw new InvalidArgumentException("toAddress must be 1-96 chars.");
        }
        if ($confirmations < 0) {
            throw new InvalidArgumentException("confirmations cannot be negative.");
        }
        if ($fromAddress !== null && mb_strlen($fromAddress) > 96) {
            throw new InvalidArgumentException("fromAddress must be <=96 chars.");
        }
    }

    public static function detected(
        IncomingTransactionId $id,
        ChainId $chainId,
        TxHash $txHash,
        BlockHeight $blockHeight,
        BlockHash $blockHash,
        ?string $fromAddress,
        string $toAddress,
        Amount $amount,
        Currency $currency,
        DateTimeImmutable $detectedAt,
    ): self {
        $tx = new self(
            id: $id,
            chainId: $chainId,
            txHash: $txHash,
            blockHeight: $blockHeight,
            blockHash: $blockHash,
            fromAddress: $fromAddress,
            toAddress: $toAddress,
            amount: $amount,
            currency: $currency,
            status: IncomingTxStatus::Detected,
            confirmations: 1,
            detectedAt: $detectedAt,
        );
        $tx->pendingEvents[] = new TransactionDetected(
            id: $id,
            chainId: $chainId,
            txHash: $txHash,
            blockHeight: $blockHeight,
            toAddress: $toAddress,
            amount: $amount,
            currency: $currency,
            occurredAt: $detectedAt,
        );
        return $tx;
    }

    public static function reconstitute(
        IncomingTransactionId $id,
        ChainId $chainId,
        TxHash $txHash,
        ?BlockHeight $blockHeight,
        ?BlockHash $blockHash,
        ?string $fromAddress,
        string $toAddress,
        Amount $amount,
        Currency $currency,
        IncomingTxStatus $status,
        int $confirmations,
        DateTimeImmutable $detectedAt,
    ): self {
        return new self(
            $id,
            $chainId,
            $txHash,
            $blockHeight,
            $blockHash,
            $fromAddress,
            $toAddress,
            $amount,
            $currency,
            $status,
            $confirmations,
            $detectedAt,
        );
    }

    public function status(): IncomingTxStatus
    {
        return $this->status;
    }

    public function confirmations(): int
    {
        return $this->confirmations;
    }

    /**
     * Обновить кэшированное количество подтверждений и (опционально) изменить статус.
     * Вызывается модулем Confirmation через его собственное представление репозитория; ничто
     * за пределами этого агрегата не имеет права менять статус.
     */
    public function applyConfirmation(int $confirmations, IncomingTxStatus $newStatus): void
    {
        if ($confirmations < $this->confirmations) {
            throw new InvalidArgumentException(
                "confirmations cannot decrease (have {$this->confirmations}, got {$confirmations})."
            );
        }
        $this->status->assertCanTransitionTo($newStatus);
        $this->status = $newStatus;
        $this->confirmations = $confirmations;
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
