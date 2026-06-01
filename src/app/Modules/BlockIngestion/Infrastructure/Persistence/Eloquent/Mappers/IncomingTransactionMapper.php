<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\BlockIngestion\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionModel;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;

final class IncomingTransactionMapper
{
    public function toDomain(IncomingTransactionModel $row): IncomingTransaction
    {
        return IncomingTransaction::reconstitute(
            id: new IncomingTransactionId($row->id),
            chainId: new ChainId($row->chain_id),
            txHash: new TxHash($row->tx_hash),
            blockHeight: $row->block_height !== null ? new BlockHeight((int) $row->block_height) : null,
            blockHash: $row->block_hash !== null ? new BlockHash($row->block_hash) : null,
            fromAddress: $row->from_address,
            toAddress: $row->to_address,
            amount: new Amount($this->amountToString($row->amount)),
            currency: new Currency($row->currency),
            status: IncomingTxStatus::from($row->status),
            confirmations: (int) $row->confirmations,
            detectedAt: DateTimeImmutable::createFromInterface($row->detected_at),
        );
    }

    /**
     * @return array{
     *     id: string, chain_id: string, tx_hash: string,
     *     block_height: int|null, block_hash: string|null,
     *     from_address: string|null, to_address: string,
     *     amount: string, currency: string,
     *     status: string, confirmations: int,
     *     detected_at: \DateTimeImmutable,
     * }
     */
    public function toRow(IncomingTransaction $tx): array
    {
        return [
            'id' => $tx->id->value,
            'chain_id' => $tx->chainId->value,
            'tx_hash' => $tx->txHash->normalized(),
            'block_height' => $tx->blockHeight?->value,
            'block_hash' => $tx->blockHash?->normalized(),
            'from_address' => $tx->fromAddress,
            'to_address' => $tx->toAddress,
            'amount' => $tx->amount->value,
            'currency' => $tx->currency->code,
            'status' => $tx->status()->value,
            'confirmations' => $tx->confirmations(),
            'detected_at' => $tx->detectedAt,
        ];
    }

    /**
     * Driver-dependent NUMERIC fetch: pgsql returns string, sqlite may return
     * an int. Normalize both to a digits-only string; drop any fractional part
     * since the column is NUMERIC(40, 0).
     */
    private function amountToString(string|int|float $raw): string
    {
        if (is_float($raw)) {
            // Принудительно отсекаем дробную часть (truncation) и подавляем экспоненту
            return number_format($raw >= 0 ? floor($raw) : ceil($raw), 0, '.', '');
        }

        $string = (string) $raw;

        if (str_contains($string, '.')) {
            $integer = explode('.', $string, 2)[0];
            return $integer === '' ? '0' : $integer;
        }

        return $string;
    }
}
