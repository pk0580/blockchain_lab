<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сохраняем "сборочные метаданные" транзакции (signing_extras).
 * Это критично для BIP-125 RBF на BTC: replacement-транзакция должна
 * использовать тот же набор UTXO (inputs), что и оригинал, иначе это
 * не replacement, а двойная трата.
 *
 * Для EVM здесь продублирован nonce / chain_id из FeeQuoteSnapshot — это
 * перестраховка: если в будущем поменяем структуру FeeQuoteSnapshot, RBF
 * не сломается.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->jsonb('signing_extras')->nullable()->after('raw_tx_hex');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropColumn('signing_extras');
        });
    }
};
