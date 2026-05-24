<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use InvalidArgumentException;

/**
 * EIP-1559 параметры:
 *  - baseFeePerGas — выгорает (burn) в каждом блоке, динамический.
 *  - maxPriorityFeePerGas — чаевые валидатору, нижняя граница для попадания в блок.
 *  - maxFeePerGas — потолок: пользователь готов платить столько за газ.
 *    Если baseFee + priority > maxFee → tx не подтвердится в этом блоке.
 *
 * Все значения в wei. Хранятся строками: maxFeePerGas может быть огромным
 * (мейннет ETH в пиковые моменты >1e12 wei = >1000 gwei). gasLimit для
 * простого native transfer = 21000 (статическое значение протокола EVM).
 */
final readonly class EvmFeeBreakdown implements FeeBreakdown
{
    public function __construct(
        public string $maxFeePerGasWei,
        public string $maxPriorityFeePerGasWei,
        public int $gasLimit,
    ) {
        $this->assertWei($maxFeePerGasWei, 'maxFeePerGasWei');
        $this->assertWei($maxPriorityFeePerGasWei, 'maxPriorityFeePerGasWei');
        if ($gasLimit < 21000) {
            throw new InvalidArgumentException(
                "gasLimit должен быть >= 21000 (минимум для нативного перевода), получено {$gasLimit}."
            );
        }
        // maxPriority <= maxFee — обязательное соотношение по EIP-1559;
        // иначе валидатор не получит обещанные чаевые.
        // После assertWei оба значения — numeric-string, но PHPStan этого не
        // знает без локального приведения типов.
        /** @var numeric-string $priority */
        $priority = $maxPriorityFeePerGasWei;
        /** @var numeric-string $max */
        $max = $maxFeePerGasWei;
        if (bccomp($priority, $max, 0) > 0) {
            throw new InvalidArgumentException(
                "maxPriorityFeePerGasWei ({$maxPriorityFeePerGasWei}) должен быть <= maxFeePerGasWei ({$maxFeePerGasWei})."
            );
        }
    }

    public function family(): ChainFamily
    {
        return ChainFamily::Evm;
    }

    /**
     * @return array{family: string, max_fee_per_gas_wei: string, max_priority_fee_per_gas_wei: string, gas_limit: int}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family()->value,
            'max_fee_per_gas_wei' => $this->maxFeePerGasWei,
            'max_priority_fee_per_gas_wei' => $this->maxPriorityFeePerGasWei,
            'gas_limit' => $this->gasLimit,
        ];
    }

    private function assertWei(string $value, string $field): void
    {
        if (! preg_match('/^[1-9][0-9]{0,77}$|^0$/', $value)) {
            throw new InvalidArgumentException(
                "{$field} must be a non-negative integer string (wei), got '{$value}'."
            );
        }
    }
}
