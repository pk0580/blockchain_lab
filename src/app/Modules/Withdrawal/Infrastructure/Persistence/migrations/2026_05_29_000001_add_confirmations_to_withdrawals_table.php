<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.3: храним число подтверждений, увиденное polling-job'ом. Колонка
 * нужна, чтобы при повторном тике не плодить идентичные `WithdrawalConfirming`
 * события (idempotency для observability) и чтобы клиент мог отобразить
 * прогресс по GET /api/v1/withdrawals/{id}.
 *
 * Без этой колонки aggregate теряет счётчик при перезагрузке — а это инвариант
 * (нельзя случайно "откатить" 4 подтверждения до 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->unsignedInteger('confirmations')->default(0)->after('tx_hash');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropColumn('confirmations');
        });
    }
};
