<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Global idempotency for POST requests
    |--------------------------------------------------------------------------
    |
    | Triggered by `IdempotencyMiddleware` on the api group. A POST request
    | carrying an `Idempotency-Key` header gets its response frozen for
    | `ttl_seconds`. Replay returns the original status + body with header
    | `X-Idempotent-Replay: true`. Same key + different body → 409.
    |
    */

    'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86400),

];
