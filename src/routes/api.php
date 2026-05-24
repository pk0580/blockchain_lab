<?php

declare(strict_types=1);

use App\Modules\Education\UI\Http\Controller\Admin\GetAdminDashboardController;
use App\Modules\Education\UI\Http\Controller\Playground\DecodeRawTxController;
use App\Modules\Education\UI\Http\Controller\Playground\GasChartController;
use App\Modules\Education\UI\Http\Controller\Playground\GenerateKeypairController;
use App\Modules\Education\UI\Http\Controller\Playground\GetMempoolController;
use App\Modules\Education\UI\Http\Controller\Playground\GetRegtestStateController;
use App\Modules\Education\UI\Http\Controller\Playground\InvalidateTipController;
use App\Modules\Education\UI\Http\Controller\Playground\MineBlocksController;
use App\Modules\Education\UI\Http\Controller\Playground\NonceConflictController;
use App\Modules\Education\UI\Http\Controller\Playground\SignMessageController;
use App\Modules\Withdrawal\UI\Http\Controller\CreateWithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('withdrawals', CreateWithdrawalController::class)
        ->name('withdrawals.create');
});

// Phase 8.4 — polling endpoint админ-дашборда.
Route::get('admin/dashboard', GetAdminDashboardController::class)->name('admin.dashboard.poll');

Route::prefix('playground')->name('playground.')->group(function (): void {
    // Phase 8.2 — crypto playgrounds.
    Route::post('keypair', GenerateKeypairController::class)->name('keypair');
    Route::post('sign', SignMessageController::class)->name('sign');
    Route::post('decode', DecodeRawTxController::class)->name('decode');

    // Phase 8.3 — Bitcoin regtest playgrounds (mempool tracker, reorg simulator).
    Route::get('regtest/mempool', GetMempoolController::class)->name('regtest.mempool');
    Route::get('regtest/state', GetRegtestStateController::class)->name('regtest.state');
    Route::post('regtest/mine', MineBlocksController::class)->name('regtest.mine');
    Route::post('regtest/invalidate-tip', InvalidateTipController::class)->name('regtest.invalidate');

    // Phase 8.3 — synthetic / educational endpoints.
    Route::get('gas/chart', GasChartController::class)->name('gas.chart');
    Route::post('nonce/simulate', NonceConflictController::class)->name('nonce.simulate');
});
