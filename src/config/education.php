<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Education content root
    |--------------------------------------------------------------------------
    | Markdown-уроки лежат на диске в `content/lessons/{NN-module}/{NN-slug}.md`.
    | Путь задаётся относительно `base_path()` (= корня репозитория, не `src/`).
    */

    'content_path' => env('EDUCATION_CONTENT_PATH', base_path('content/lessons')),

    /*
    |--------------------------------------------------------------------------
    | Playground proxy timeouts
    |--------------------------------------------------------------------------
    | HttpPlaygroundClient использует эти значения при обращении к signing-svc.
    */

    'playground' => [
        'timeout_seconds' => (int) env('EDUCATION_PLAYGROUND_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bitcoin regtest RPC (для Reorg / Mempool playground'ов)
    |--------------------------------------------------------------------------
    | Совпадает с тем что использует BlockIngestion, но изолированный конфиг
    | чтобы Education не зависел от BlockIngestion-неймспейса.
    */

    'regtest' => [
        'url' => env('BITCOIN_RPC_URL', 'http://bitcoin-regtest:18443'),
        'user' => env('BITCOIN_RPC_USER', 'bitcoin'),
        'password' => env('BITCOIN_RPC_PASSWORD', 'bitcoin'),
    ],

];
