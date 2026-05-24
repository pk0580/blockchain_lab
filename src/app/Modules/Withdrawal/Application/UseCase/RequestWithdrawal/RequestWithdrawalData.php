<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;

/**
 * DTO для use-case'а Request withdrawal. Все значения уже валидированы
 * Form Request на UI-слое; здесь приходит structured input.
 *
 * `$priority` хранится строкой (low|standard|high) сознательно — Withdrawal::
 * Application не должен импортировать Fee::Domain (см. ModuleBoundariesTest);
 * конвертация в Fee::Domain::FeePriority происходит уже внутри Fee::Application
 * через EstimateFeeData::fromPrimitives.
 */
final readonly class RequestWithdrawalData
{
    public function __construct(
        public WalletId $walletId,
        public ChainId $chainId,
        public Address $toAddress,
        public WithdrawalAmount $amount,
        public Currency $currency,
        public string $priority,
        public IdempotencyKey $idempotencyKey,
    ) {}

    /**
     * @return array<string, string>
     */
    public function fingerprint(): array
    {
        return [
            'wallet_id' => $this->walletId->value,
            'chain_id' => $this->chainId->value,
            'to_address' => $this->toAddress->value,
            'amount' => $this->amount->value,
            'currency' => $this->currency->value,
            'priority' => $this->priority,
        ];
    }
}
