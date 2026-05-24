<?php

declare(strict_types=1);

return [
    'bitcoin' => [
        'rpc_user' => env('BITCOIN_RPC_USER', 'bitcoin'),
        'rpc_password' => env('BITCOIN_RPC_PASSWORD', 'bitcoin'),
        'timeout_seconds' => (int) env('BITCOIN_RPC_TIMEOUT', 5),
        'connect_timeout_seconds' => (int) env('BITCOIN_RPC_CONNECT_TIMEOUT', 2),
        'retries' => (int) env('BITCOIN_RPC_RETRIES', 1),
        'retry_backoff_ms' => (int) env('BITCOIN_RPC_RETRY_BACKOFF_MS', 150),
    ],

    'directory' => [
        'redis_connection' => env('BLOCK_INGESTION_REDIS_CONNECTION', 'default'),
        'key_prefix' => env('BLOCK_INGESTION_DIRECTORY_PREFIX', 'watched'),
    ],

    'scanner' => [
        'max_blocks_per_tick' => (int) env('BLOCK_INGESTION_MAX_BLOCKS_PER_TICK', 25),
    ],
];
