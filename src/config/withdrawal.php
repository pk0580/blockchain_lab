<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Hot wallets per chain
    |--------------------------------------------------------------------------
    | Источник средств для исходящих транзакций. (address, hd_seed_ref, path)
    | резолвится при первом обращении через SigningClient::deriveAddress.
    */
    'hot_wallets' => [
        'bitcoin-regtest' => [
            'address' => env('WITHDRAWAL_BTC_HOT_ADDRESS'),
            'hd_seed_ref' => env('WITHDRAWAL_BTC_SEED_REF'),
            'derivation_path' => env('WITHDRAWAL_BTC_PATH', "m/44'/0'/0'/1/0"),
        ],
        'ethereum-sepolia' => [
            'address' => env('WITHDRAWAL_ETH_HOT_ADDRESS'),
            'hd_seed_ref' => env('WITHDRAWAL_ETH_SEED_REF'),
            'derivation_path' => env('WITHDRAWAL_ETH_PATH', "m/44'/60'/0'/1/0"),
        ],
    ],

    'default_priority' => env('WITHDRAWAL_DEFAULT_PRIORITY', 'standard'),

    'stuck_after_seconds' => (int) env('WITHDRAWAL_STUCK_AFTER_SECONDS', 900),

    'chain_pause_ttl_seconds' => (int) env('WITHDRAWAL_CHAIN_PAUSE_TTL_SECONDS', 86400),

    'rbf' => [
        // +25% bump на replacement: 12500 / 10000 = 1.25.
        'fee_multiplier_bps' => (int) env('WITHDRAWAL_RBF_FEE_BPS', 12500),
    ],

    'polling' => [
        // Сколько withdrawals опрашивает один тик confirmations job'а.
        'confirmations_batch_size' => (int) env('WITHDRAWAL_CONFIRMATIONS_BATCH', 100),
    ],

    'nonce' => [
        'timeout_seconds' => (int) env('WITHDRAWAL_NONCE_TIMEOUT', 5),
        'connect_timeout_seconds' => (int) env('WITHDRAWAL_NONCE_CONNECT_TIMEOUT', 2),
        'retries' => (int) env('WITHDRAWAL_NONCE_RETRIES', 1),
        'retry_backoff_ms' => (int) env('WITHDRAWAL_NONCE_RETRY_BACKOFF_MS', 150),
    ],
];
