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
            // NUMERIC(40,0): amount всегда целочисленный (minor units).
            // Money сам отвергнет дробное/мусорное значение — порча данных
            // всплывёт громко, а не молча округлится/обрежется.
            money: new Money((string) $row->amount, $row->currency),
        );
    }
}
