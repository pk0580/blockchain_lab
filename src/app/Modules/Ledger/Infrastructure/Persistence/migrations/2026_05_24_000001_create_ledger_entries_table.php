<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->string('chain_id', 64);
            $table->string('direction', 8);            // credit | debit
            $table->decimal('amount', total: 40, places: 0);
            $table->string('currency', 10);
            $table->string('operation_type', 32);       // deposit | reorg_reversal | withdrawal | fee
            $table->string('operation_ref', 96);
            $table->string('related_tx_hash', 80)->nullable();
            $table->unsignedBigInteger('block_height')->nullable();
            $table->uuid('reverses_entry_id')->nullable();
            $table->string('status', 16);               // pending | confirmed | reversed
            $table->timestampTz('created_at');

            $table->unique(['operation_type', 'operation_ref'], 'ledger_op_unique');
            $table->index(['wallet_id', 'status'], 'ledger_wallet_status_idx');
            $table->index(['chain_id', 'block_height', 'status'], 'ledger_chain_block_status_idx');
            $table->index(['reverses_entry_id'], 'ledger_reverses_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
