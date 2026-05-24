<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table): void {
            $table->string('chain_id', 64);
            $table->unsignedBigInteger('height');
            $table->string('hash', 80);
            $table->string('parent_hash', 80);
            $table->timestampTz('timestamp');
            $table->timestampTz('scanned_at');

            $table->primary(['chain_id', 'height']);
            $table->unique(['chain_id', 'hash'], 'blocks_chain_hash_unique');
            $table->index(['chain_id', 'scanned_at']);
        });

        Schema::create('scan_cursors', function (Blueprint $table): void {
            $table->string('chain_id', 64)->primary();
            $table->bigInteger('last_scanned_height')->default(0);
            $table->bigInteger('last_seen_head_height')->default(0);
            $table->timestampTz('updated_at');
        });

        Schema::create('incoming_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('chain_id', 64);
            $table->string('tx_hash', 80);
            $table->unsignedBigInteger('block_height')->nullable();
            $table->string('block_hash', 80)->nullable();
            $table->string('from_address', 96)->nullable();
            $table->string('to_address', 96);
            $table->decimal('amount', total: 40, places: 0);
            $table->string('currency', 10);
            $table->string('status', 16);
            $table->unsignedInteger('confirmations')->default(0);
            $table->timestampTz('detected_at');

            $table->unique(
                ['chain_id', 'tx_hash', 'to_address'],
                'incoming_tx_chain_hash_recipient_unique',
            );
            $table->index(['chain_id', 'to_address', 'status'], 'incoming_tx_recipient_status_idx');
            $table->index(['chain_id', 'status', 'block_height'], 'incoming_tx_status_height_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_transactions');
        Schema::dropIfExists('scan_cursors');
        Schema::dropIfExists('blocks');
    }
};
