<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Probe timeouts
    |--------------------------------------------------------------------------
    | Намеренно более жёсткие, чем у обычных RPC-клиентов: probe это «жив ли узел»,
    | долго ждать нет смысла. Если узел не отвечает за `timeout_seconds` — он
    | объявляется Unhealthy.
    */
    'bitcoin' => [
        'timeout_seconds' => (int) env('NODE_HEALTH_BTC_TIMEOUT', 3),
        'connect_timeout_seconds' => (int) env('NODE_HEALTH_BTC_CONNECT_TIMEOUT', 2),
    ],

    'evm' => [
        'timeout_seconds' => (int) env('NODE_HEALTH_EVM_TIMEOUT', 3),
        'connect_timeout_seconds' => (int) env('NODE_HEALTH_EVM_CONNECT_TIMEOUT', 2),
    ],
];
