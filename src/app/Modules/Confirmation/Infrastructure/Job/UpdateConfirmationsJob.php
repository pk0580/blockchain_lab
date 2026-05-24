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
 * Per-chain confirmation tick. Idempotent: re-runs are safe because the
 * action only writes when the (status, confirmations) tuple has actually
 * changed.
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
