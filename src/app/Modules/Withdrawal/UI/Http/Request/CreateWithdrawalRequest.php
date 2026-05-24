<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\UI\Http\Request;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\RequestWithdrawalData;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use Illuminate\Foundation\Http\FormRequest;

final class CreateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var string $defaultPriority */
        $defaultPriority = (string) config('withdrawal.default_priority', 'standard');

        return [
            'wallet_id' => ['required', 'string', 'min:1', 'max:64'],
            'chain_id' => ['required', 'string', 'min:3', 'max:64'],
            'to_address' => ['required', 'string', 'min:10', 'max:128'],
            'amount' => ['required', 'string', 'regex:/^[1-9][0-9]{0,39}$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9]{1,9}$/'],
            'priority' => ['sometimes', 'string', 'in:low,standard,high'],
            '_default_priority' => ['nullable', 'string', 'in:'.$defaultPriority],
        ];
    }

    public function idempotencyKey(): IdempotencyKey
    {
        $header = $this->header('Idempotency-Key');
        if (! is_string($header) || $header === '') {
            abort(422, 'Missing Idempotency-Key header.');
        }
        return new IdempotencyKey($header);
    }

    public function toDto(): RequestWithdrawalData
    {
        $priority = (string) ($this->input('priority') ?? config('withdrawal.default_priority', 'standard'));

        return new RequestWithdrawalData(
            walletId: new WalletId((string) $this->input('wallet_id')),
            chainId: new ChainId((string) $this->input('chain_id')),
            toAddress: new Address((string) $this->input('to_address')),
            amount: new WithdrawalAmount((string) $this->input('amount')),
            currency: new Currency((string) $this->input('currency')),
            priority: $priority,
            idempotencyKey: $this->idempotencyKey(),
        );
    }
}
