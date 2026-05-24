<?php

declare(strict_types=1);

return [
    'evm' => [
        'gas_limit_transfer' => (int) env('FEE_EVM_GAS_LIMIT_TRANSFER', 21000),
        'priority_percentiles' => [
            'low' => (int) env('FEE_EVM_PERCENTILE_LOW', 10),
            'standard' => (int) env('FEE_EVM_PERCENTILE_STANDARD', 50),
            'high' => (int) env('FEE_EVM_PERCENTILE_HIGH', 90),
        ],
        'base_fee_multiplier' => (float) env('FEE_EVM_BASE_FEE_MULTIPLIER', 2.0),
        'timeout_seconds' => (int) env('FEE_EVM_TIMEOUT', 5),
        'connect_timeout_seconds' => (int) env('FEE_EVM_CONNECT_TIMEOUT', 2),
        'retries' => (int) env('FEE_EVM_RETRIES', 1),
        'retry_backoff_ms' => (int) env('FEE_EVM_RETRY_BACKOFF_MS', 150),
    ],
    'bitcoin' => [
        'targets' => [
            'low' => (int) env('FEE_BTC_TARGET_LOW', 6),
            'standard' => (int) env('FEE_BTC_TARGET_STANDARD', 3),
            'high' => (int) env('FEE_BTC_TARGET_HIGH', 1),
        ],
        'modes' => [
            'low' => env('FEE_BTC_MODE_LOW', 'ECONOMICAL'),
            'standard' => env('FEE_BTC_MODE_STANDARD', 'ECONOMICAL'),
            'high' => env('FEE_BTC_MODE_HIGH', 'CONSERVATIVE'),
        ],
        'min_sat_per_vbyte' => (int) env('FEE_BTC_MIN_SAT_PER_VBYTE', 1),
    ],
];
