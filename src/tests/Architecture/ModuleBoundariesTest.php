<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Module boundary rules
|--------------------------------------------------------------------------
|
| One module's Domain must not import another module's Domain directly —
| with one exception: the `Network` module is the platform's shared kernel.
| It defines ChainFamily, BlockHeight, TxHash, Address (the VO), and the
| ChainAdapter contract. Every other module reads from it.
|
| Cross-module integration outside the shared kernel must go through
| published events or public Application Actions, not direct imports.
|
| See STEPS.md §2 (13 bounded contexts) and ADR 0001.
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

// Modules every other module is allowed to depend on (shared kernel).
$sharedKernel = ['Network'];

foreach ($modules as $module) {
    foreach ($modules as $other) {
        if ($module === $other || in_array($other, $sharedKernel, strict: true)) {
            continue;
        }

        arch("module [{$module}::Domain] does not depend on module [{$other}]")
            ->expect("App\\Modules\\{$module}\\Domain")
            ->not->toUse("App\\Modules\\{$other}");

        arch("module [{$module}::Application] does not reach into [{$other}::Domain]")
            ->expect("App\\Modules\\{$module}\\Application")
            ->not->toUse("App\\Modules\\{$other}\\Domain");
    }
}
