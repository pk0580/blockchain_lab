<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Infrastructure\Job;

use App\Modules\Confirmation\Application\UseCase\UpdateConfirmations\UpdateConfirmationsAction;
use App\Modules\Confirmation\Application\UseCase\UpdateConfirmations\UpdateConfirmationsData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Периодическая задача обновления подтверждений для конкретной сети.
 * Идемпотентна: повторные запуски безопасны, так как действие выполняется
 * только при изменении кортежа (status, confirmations).
 */
final class UpdateConfirmationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $chainId)
    {
        $this->onQueue("confirmations.{$chainId}");
    }

    public function handle(UpdateConfirmationsAction $action): void
    {
        $action->handle(new UpdateConfirmationsData($this->chainId));
    }
}
