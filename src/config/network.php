<?php

declare(strict_types=1);

return [
    'signing' => [
        'url' => env('SIGNING_SVC_URL', 'http://signing-svc:8080'),
        'bearer_token' => env('SIGNING_SVC_BEARER_TOKEN'),
        'timeout_seconds' => (int) env('SIGNING_SVC_TIMEOUT', 5),
        'connect_timeout_seconds' => (int) env('SIGNING_SVC_CONNECT_TIMEOUT', 2),
        'retries' => (int) env('SIGNING_SVC_RETRIES', 2),
        'retry_backoff_ms' => (int) env('SIGNING_SVC_RETRY_BACKOFF_MS', 150),
    ],

    'rpc' => [
        'bitcoin_regtest' => env('BITCOIN_REGTEST_RPC', 'http://bitcoin-regtest:18443'),
        'ethereum_sepolia' => env('ETHEREUM_SEPOLIA_RPC', 'https://rpc.sepolia.org'),
        'tron_shasta' => env('TRON_SHASTA_RPC', 'https://api.shasta.trongrid.io'),
        'polygon_amoy' => env('POLYGON_AMOY_RPC', 'https://rpc-amoy.polygon.technology'),
    ],
];
