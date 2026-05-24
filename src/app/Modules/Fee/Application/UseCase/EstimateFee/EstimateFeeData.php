<?php

declare(strict_types=1);

namespace App\Modules\Fee\Application\UseCase\EstimateFee;

use App\Modules\Fee\Domain\ValueObject\FeePriority;
use App\Modules\Network\Domain\ValueObject\ChainId;

final readonly class EstimateFeeData
{
    public function __construct(
        public ChainId $chainId,
        public FeePriority $priority,
    ) {}

    /**
     * Конструктор-факториал для cross-module вызовов: Withdrawal::Application
     * не должен импортировать `Fee::Domain::FeePriority`, поэтому конвертация
     * происходит здесь.
     */
    public static function fromPrimitives(string $chainId, string $priority): self
    {
        return new self(
            chainId: new ChainId($chainId),
            priority: FeePriority::fromString($priority),
        );
    }
}
