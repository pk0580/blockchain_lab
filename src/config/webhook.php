<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP dispatch
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout_seconds' => (int) env('WEBHOOK_HTTP_TIMEOUT', 5),
        'connect_timeout_seconds' => (int) env('WEBHOOK_HTTP_CONNECT_TIMEOUT', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry policy (exponential backoff)
    |--------------------------------------------------------------------------
    | max_attempts включает первую попытку. backoff_seconds[i] — задержка перед
    | (i+2)-й попыткой. После исчерпания массива берётся последний элемент.
    */
    'retry' => [
        'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 5),
        'backoff_seconds' => [30, 120, 600, 3600],
    ],

    /*
    |--------------------------------------------------------------------------
    | Безопасность URL
    |--------------------------------------------------------------------------
    | По умолчанию принимаем только https. Для тестов / локальной разработки
    | можно временно включить через WEBHOOK_ALLOW_INSECURE_URLS=true.
    */
    'allow_insecure_urls' => filter_var(
        env('WEBHOOK_ALLOW_INSECURE_URLS', false),
        FILTER_VALIDATE_BOOLEAN,
    ),
];
