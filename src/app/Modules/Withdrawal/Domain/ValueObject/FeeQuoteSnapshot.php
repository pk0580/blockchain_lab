<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Снимок Fee::Domain::ValueObject\FeeQuote — примитивы, без импорта Fee.
 *
 * Withdrawal::Application получает Fee через `Fee::Application::EstimateFeeAction`
 * (Application↔Application между модулями допустим, Domain↔чужой Domain — нет),
 * а Domain хранит лишь «расплющенные» байты для сериализации в `withdrawals.fee_breakdown_json`.
 *
 * Поле `breakdown` хранит массив, полностью совместимый с `FeeBreakdown::toArray()`,
 * содержит ключ `family` ('bitcoin' | 'evm') и family-specific параметры
 * (sat_per_vbyte для BTC, max_fee_per_gas_wei + max_priority_fee_per_gas_wei + gas_limit для EVM).
 */
final readonly class FeeQuoteSnapshot
{
    /**
     * @param array<string, scalar> $breakdown
     */
    public function __construct(
        public string $priority,
        public array $breakdown,
        public DateTimeImmutable $estimatedAt,
    ) {
        if ($priority === '' || strlen($priority) > 16) {
            throw new InvalidArgumentException("FeeQuoteSnapshot priority must be a short string, got '{$priority}'.");
        }
        if (! isset($breakdown['family']) || ! is_string($breakdown['family']) || $breakdown['family'] === '') {
            throw new InvalidArgumentException('FeeQuoteSnapshot breakdown must carry a non-empty string "family" key.');
        }
    }

    public function family(): string
    {
        /** @var string $family */
        $family = $this->breakdown['family'];
        return $family;
    }
}
