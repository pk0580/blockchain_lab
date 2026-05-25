<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Infrastructure\Job;

use App\Modules\Idempotency\Domain\Contract\Clock;
use App\Modules\Idempotency\Domain\Contract\IdempotencyStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Раз в сутки чистит просроченные idempotency-ключи.
 * Шумные ошибки логируются, но не пробрасываются — job не должен застревать
 * в retry-loop из-за временной недоступности БД.
 */
final class CleanupExpiredIdempotencyKeysJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function handle(IdempotencyStore $store, Clock $clock): void
    {
        $now = $clock->now();
        $deleted = $store->deleteExpired($now);

        if ($deleted > 0) {
            Log::info('idempotency.cleanup', [
                'deleted' => $deleted,
                'as_of' => $now->format(DATE_ATOM),
            ]);
        }
    }
}
