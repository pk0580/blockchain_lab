<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Adapter;

use App\Modules\Ledger\Domain\Contract\ConfirmedTransactionView;
use App\Modules\Ledger\Domain\ReadModel\ConfirmedTransactionData;
use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionLookupModel;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;

final readonly class EloquentConfirmedTransactionView implements ConfirmedTransactionView
{
    public function __construct(private ChainRepository $chains) {}

    public function findById(string $incomingTransactionId): ?ConfirmedTransactionData
    {
        $row = IncomingTransactionLookupModel::query()
            ->whereKey($incomingTransactionId)
            ->first(['id', 'chain_id', 'tx_hash', 'block_height', 'to_address', 'amount', 'currency']);

        if ($row === null || $row->block_height === null) {
            return null;
        }

        $chain = $this->chains->findById(new ChainId($row->chain_id));
        if ($chain === null) {
            return null;
        }

        return new ConfirmedTransactionData(
            incomingTransactionId: $row->id,
            chainId: $chain->id,
            family: $chain->family,
            txHash: new TxHash($row->tx_hash),
            blockHeight: (int) $row->block_height,
            toAddress: $row->to_address,
            money: new Money($this->amountToString($row->amount), $row->currency),
        );
    }

    private function amountToString(string|int|float $raw): string
    {
        if (is_int($raw)) {
            return (string) $raw;
        }
        if (is_float($raw)) {
            return number_format($raw, 0, '.', '');
        }
        if (str_contains($raw, '.')) {
            $integer = explode('.', $raw, 2)[0];
            return $integer === '' ? '0' : $integer;
        }
        return $raw;
    }
}
