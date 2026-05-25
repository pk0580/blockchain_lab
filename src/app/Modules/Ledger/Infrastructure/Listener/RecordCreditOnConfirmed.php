<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Listener;

use App\Modules\Confirmation\Domain\Event\TransactionConfirmed;
use App\Modules\Ledger\Application\UseCase\RecordLedgerCredit\RecordLedgerCreditAction;
use App\Modules\Ledger\Application\UseCase\RecordLedgerCredit\RecordLedgerCreditData;
use App\Modules\Ledger\Domain\Exception\WalletOwnershipMissingException;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;

/**
 * Мост Confirmation::TransactionConfirmed → Ledger::RecordLedgerCredit.
 *
 * Запускает зачисление при достижении requiredConfirmations
 * (см. GUIDE.md, Урок 8 «Зачисление при подтверждении»).
 *
 * WalletOwnershipMissingException гасится в warning: не каждое подтверждение
 * нашего «watched» адреса означает принадлежность нашему кошельку — например,
 * адрес мог быть зарегистрирован в Directory без wallet_id.
 *
 * @see \GUIDE.md  Урок 8 (#урок-8--двойная-бухгалтерия-ledger)
 */
final readonly class RecordCreditOnConfirmed
{
    public function __construct(
        private Container $container,
        private LoggerInterface $logger,
    ) {}

    public function handle(TransactionConfirmed $event): void
    {
        /** @var RecordLedgerCreditAction $action */
        $action = $this->container->make(RecordLedgerCreditAction::class);

        try {
            $action->handle(new RecordLedgerCreditData(
                incomingTransactionId: $event->incomingTransactionId,
            ));
        } catch (WalletOwnershipMissingException $e) {
            $this->logger->warning('ledger.credit.no_wallet_owner', [
                'incoming_transaction_id' => $event->incomingTransactionId,
                'chain_id' => $event->chainId->value,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
