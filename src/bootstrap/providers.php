<?php

declare(strict_types=1);

use App\Modules\Address\Infrastructure\Provider\AddressServiceProvider;
use App\Modules\BlockIngestion\Infrastructure\Provider\BlockIngestionServiceProvider;
use App\Modules\Confirmation\Infrastructure\Provider\ConfirmationServiceProvider;
use App\Modules\Education\Infrastructure\Provider\EducationServiceProvider;
use App\Modules\Fee\Infrastructure\Provider\FeeServiceProvider;
use App\Modules\Idempotency\Infrastructure\Provider\IdempotencyServiceProvider;
use App\Modules\Ledger\Infrastructure\Provider\LedgerServiceProvider;
use App\Modules\Network\Infrastructure\Provider\NetworkServiceProvider;
use App\Modules\NodeHealth\Infrastructure\Provider\NodeHealthServiceProvider;
use App\Modules\ReorgDetection\Infrastructure\Provider\ReorgDetectionServiceProvider;
use App\Modules\Webhook\Infrastructure\Provider\WebhookServiceProvider;
use App\Modules\Withdrawal\Infrastructure\Provider\WithdrawalServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    NetworkServiceProvider::class,
    BlockIngestionServiceProvider::class,
    AddressServiceProvider::class,
    ConfirmationServiceProvider::class,
    ReorgDetectionServiceProvider::class,
    LedgerServiceProvider::class,
    FeeServiceProvider::class,
    WithdrawalServiceProvider::class,
    // NodeHealth идёт ПОСЛЕ Network/Withdrawal: его register() перебивает
    // RpcEndpointPicker биндинг на HealthBased-вариант.
    NodeHealthServiceProvider::class,
    WebhookServiceProvider::class,
    IdempotencyServiceProvider::class,
    EducationServiceProvider::class,
];
