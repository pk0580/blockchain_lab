<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Provider;

use App\Modules\Confirmation\Domain\Event\TransactionConfirmed;
use App\Modules\Ledger\Domain\Contract\ConfirmedTransactionView;
use App\Modules\Ledger\Domain\Contract\WalletOwnership;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
use App\Modules\Ledger\Infrastructure\Adapter\EloquentConfirmedTransactionView;
use App\Modules\Ledger\Infrastructure\Adapter\EloquentWalletOwnership;
use App\Modules\Ledger\Infrastructure\Listener\RecordCreditOnConfirmed;
use App\Modules\Ledger\Infrastructure\Listener\ReverseLedgerOnReorg;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Repositories\EloquentLedgerEntryRepository;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

final class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LedgerEntryRepository::class, EloquentLedgerEntryRepository::class);
        $this->app->singleton(ConfirmedTransactionView::class, EloquentConfirmedTransactionView::class);
        $this->app->singleton(WalletOwnership::class, EloquentWalletOwnership::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(TransactionConfirmed::class, RecordCreditOnConfirmed::class);
        $events->listen(ReorgDetected::class, ReverseLedgerOnReorg::class);
    }
}
