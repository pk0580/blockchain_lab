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
 * Слушатель Confirmation-события. Owner резолвится в Action; если адрес
 * принадлежит «чужому» (не нашему) кошельку — Action бросит
 * WalletOwnershipMissingException. На этом уровне просто пишем warning'ом
 * в лог, потому что не каждое подтверждение нашего «watched» адреса означает
 * принадлежность одному из наших кошельков (например, тестовая регистрация
 * в Directory без wallet_id в таблице addresses).
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
