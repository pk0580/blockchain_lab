<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nonce_assignments', function (Blueprint $table): void {
            $table->string('chain_id', 64);
            $table->string('hot_address', 96);
            $table->unsignedBigInteger('nonce');
            $table->uuid('withdrawal_id')->nullable();  // Заполняется после привязки к withdrawal
            $table->timestampTz('allocated_at');
            $table->timestampTz('used_at')->nullable();

            // Композитный PK = логическая уникальность (chain, hot, nonce).
            // Дополнительный UNIQUE не нужен — PK уже его обеспечивает и работает на всех СУБД.
            $table->primary(['chain_id', 'hot_address', 'nonce'], 'nonce_assignments_pkey');

            $table->index(
                ['chain_id', 'hot_address', 'used_at'],
                'nonce_assignments_chain_hot_used_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nonce_assignments');
    }
};
