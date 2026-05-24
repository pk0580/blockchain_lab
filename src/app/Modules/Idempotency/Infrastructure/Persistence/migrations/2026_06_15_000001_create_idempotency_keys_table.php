<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7.3 — Глобальный idempotency store.
 *
 *  - `key` — клиентский UUID/ULID/etc, длина 8..120 (см. VO).
 *  - `request_hash` — sha256(method + path + body), 64 hex chars.
 *  - `response_status` + `response_body` — замороженный ответ.
 *  - `expires_at` индексируется для cleanup-job'а.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->string('key', 120)->primary();
            $table->char('request_hash', 64);
            $table->string('method', 10);
            $table->string('path', 500);
            $table->unsignedInteger('response_status');
            $table->text('response_body');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');

            $table->index('expires_at', 'idempotency_keys_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
