<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\TxBuilder;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Withdrawal\Domain\Contract\TxBuilder;
use App\Modules\Withdrawal\Domain\Contract\TxBuilderRegistry;
use RuntimeException;

final readonly class ConfigurableTxBuilderRegistry implements TxBuilderRegistry
{
    /**
     * @param array<string, TxBuilder> $byFamily
     */
    public function __construct(private array $byFamily) {}

    public function for(ChainFamily $family): TxBuilder
    {
        return $this->byFamily[$family->value]
            ?? throw new RuntimeException(
                "No TxBuilder registered for family '{$family->value}'."
            );
    }
}
