<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Application\UseCase\InitChainCursor;

final readonly class InitChainCursorData
{
    public function __construct(public string $chainId) {}
}
