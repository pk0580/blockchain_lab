<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations;

use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookupRegistry;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Exception\WithdrawalNotFoundException;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Опрашивает ноду про конкретный withdrawal и продвигает state machine:
 *
 *   Broadcasted ──(>=1 conf)──▶ Confirming ──(>=required)──▶ Confirmed
 *
 * Аналог Confirmation::UpdateConfirmationsAction (Урок 6), но для нашей собственной
 * исходящей транзакции. Подробнее — GUIDE.md, Урок 6, раздел «Подтверждения для
 * исходящих транзакций».
 *
 * Реализация наблюдения зависит от семейства: EVM → {@see EvmWithdrawalConfirmationLookup}
 * (eth_getTransactionByHash + eth_blockNumber).
 *
 * Идемпотентность: метод `Withdrawal::markAsConfirming` no-op, если confirmations
 * не изменилось — повторные тики не плодят событий.
 *
 * ⚠️ Action не перезаписывает Confirming → Broadcasted, если узел временно
 * показал 0 (например, после короткой реорганизации). Регресс счётчика
 * обрабатывает ReorgDetection через свой rollback-путь.
 *
 * @see \GUIDE.md  Урок 6 (#урок-6--подтверждения-и-финализация)
 */
final readonly class UpdateWithdrawalConfirmationsAction
{
    public function __construct(
        private ChainRepository $chains,
        private WithdrawalRepository $repo,
        private WithdrawalConfirmationLookupRegistry $lookups,
        private WithdrawalEventDispatcher $events,
        private DatabaseManager $db,
    ) {}

    public function handle(UpdateWithdrawalConfirmationsData $data): UpdateWithdrawalConfirmationsResult
    {
        $withdrawalId = new WithdrawalId($data->withdrawalId);
        $withdrawal = $this->repo->findById($withdrawalId)
            ?? throw WithdrawalNotFoundException::byId($withdrawalId);

        if (! $this->isPollable($withdrawal->status())) {
            return new UpdateWithdrawalConfirmationsResult(
                status: $withdrawal->status(),
                confirmations: $withdrawal->confirmations(),
                changed: false,
                dropped: false,
            );
        }

        $txHash = $withdrawal->txHash()
            ?? throw new RuntimeException(
                "Withdrawal '{$withdrawal->id->value}' is in status {$withdrawal->status()->value} but has no tx_hash."
            );

        $chain = $this->chains->findById($withdrawal->chainId)
            ?? throw ChainNotFoundException::byId($withdrawal->chainId);

        $observation = $this->lookups
            ->for($chain->family)
            ->observe($chain, $txHash);

        if ($observation->dropped) {
            return new UpdateWithdrawalConfirmationsResult(
                status: $withdrawal->status(),
                confirmations: $withdrawal->confirmations(),
                changed: false,
                dropped: true,
            );
        }

        if ($observation->isPending()) {
            return new UpdateWithdrawalConfirmationsResult(
                status: $withdrawal->status(),
                confirmations: $withdrawal->confirmations(),
                changed: false,
                dropped: false,
            );
        }

        $now = new DateTimeImmutable();
        $required = $chain->confirmationRequirement->requiredConfirmations;
        $previous = $withdrawal->status();
        $previousConfirmations = $withdrawal->confirmations();

        $events = [];
        $this->db->transaction(function () use (
            $withdrawal,
            $observation,
            $required,
            $now,
            &$events,
        ): void {
            if ($observation->confirmations >= $required) {
                if ($withdrawal->status() !== WithdrawalStatus::Confirmed) {
                    $withdrawal->markAsConfirmed($observation->confirmations, $now);
                }
            } else {
                $withdrawal->markAsConfirming($observation->confirmations, $now);
            }
            $this->repo->save($withdrawal);
            $events = $withdrawal->pullPendingEvents();
        });

        foreach ($events as $event) {
            $this->events->dispatch($event);
        }

        $changed = $withdrawal->status() !== $previous
            || $withdrawal->confirmations() !== $previousConfirmations;

        return new UpdateWithdrawalConfirmationsResult(
            status: $withdrawal->status(),
            confirmations: $withdrawal->confirmations(),
            changed: $changed,
            dropped: false,
        );
    }

    private function isPollable(WithdrawalStatus $status): bool
    {
        return match ($status) {
            WithdrawalStatus::Broadcasted, WithdrawalStatus::Confirming => true,
            default => false,
        };
    }
}
