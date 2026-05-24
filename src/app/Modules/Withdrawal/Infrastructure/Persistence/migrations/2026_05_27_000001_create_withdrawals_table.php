<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('wallet_id', 64);
            $table->string('chain_id', 64);
            $table->string('hot_address', 96);     // denormalized для RBF / lookup
            $table->string('to_address', 128);
            $table->decimal('amount', 40, 0);
            $table->string('currency', 10);

            $table->string('fee_priority', 16);
            $table->json('fee_breakdown_json');

            $table->unsignedBigInteger('nonce')->nullable();   // только EVM
            $table->string('tx_hash', 80)->nullable();         // появляется после Broadcasted
            $table->text('raw_tx_hex')->nullable();            // храним для RBF/resend

            $table->string('status', 20);
            $table->text('failure_reason')->nullable();
            $table->uuid('replacement_of')->nullable();        // Phase 6.3 связь reverse-chain
            $table->string('idempotency_key', 120);
            $table->unsignedInteger('version')->default(0);

            $table->timestampTz('requested_at');
            $table->timestampTz('broadcast_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();

            $table->unique('idempotency_key', 'withdrawals_idempotency_key_unique');
            // tx_hash может быть NULL до broadcast; partial UNIQUE по
            // (chain_id, tx_hash) — стандартный PG-приём, но в Laravel
            // schema builder для cross-DB поддержки делаем простой UNIQUE,
            // он позволяет несколько NULL в большинстве СУБД.
            $table->unique(['chain_id', 'tx_hash'], 'withdrawals_chain_tx_hash_unique');

            $table->index(['chain_id', 'status'], 'withdrawals_chain_status_idx');
            $table->index(['chain_id', 'hot_address', 'status'], 'withdrawals_chain_hot_status_idx');
            $table->index('wallet_id', 'withdrawals_wallet_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
