<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Layer boundary rules
|--------------------------------------------------------------------------
|
| Enforced via Pest 4 arch() against the Module-First DDD layout
| (see docs/architecture/overview.md and STEPS.md §2).
|
| Pest's ignoring() accepts namespace prefixes (not glob patterns), so we
| enumerate modules explicitly and emit one rule per module/layer.
|
*/

$modules = [
    'Network',
    'Address',
    'BlockIngestion',
    'ReorgDetection',
    'Transaction',
    'Confirmation',
    'Ledger',
    'Wallet',
    'Withdrawal',
    'Fee',
    'NodeHealth',
    'Webhook',
    'Idempotency',
    'Education',
];

foreach ($modules as $module) {
    arch("module [{$module}::Domain] is framework-free")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->not->toUse([
            'Illuminate',
            'Symfony',
            'Carbon\Carbon',                 // mutable Carbon forbidden in Domain
        ]);

    arch("module [{$module}::Domain] does not call facades or globals")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->not->toUse([
            'Illuminate\Support\Facades\Auth',
            'Illuminate\Support\Facades\DB',
            'Illuminate\Support\Facades\Cache',
            'Illuminate\Support\Facades\Request',
            'Illuminate\Support\Facades\Log',
            'Illuminate\Support\Facades\Mail',
        ]);

    arch("module [{$module}::Application] is HTTP- and Eloquent-free")
        ->expect("App\\Modules\\{$module}\\Application")
        ->not->toUse([
            'Illuminate\Http\Request',
            'Illuminate\Database\Eloquent\Model',
        ]);
}

arch('strict types enforced across module code')
    ->expect('App\Modules')
    ->toUseStrictTypes();
