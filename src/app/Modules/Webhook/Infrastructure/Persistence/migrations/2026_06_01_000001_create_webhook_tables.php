<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook через Outbox pattern.
 *
 *  - `outbox_messages` — буфер событий между записью aggregate'а и доставкой.
 *  - `webhook_subscriptions` — кто куда подписался.
 *  - `webhook_deliveries` — конкретная попытка доставки (outbox × subscription).
 *
 * UNIQUE на (outbox_id, subscription_id) в deliveries: повторный запуск
 * `PublishOutboxAction` не плодит duplicate delivery'и.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_name', 64);
            $table->string('aggregate_id', 96);
            $table->jsonb('payload');
            $table->timestampTz('created_at');
            $table->timestampTz('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->index(['published_at', 'created_at'], 'outbox_unpublished_idx');
            $table->index('event_name', 'outbox_event_idx');
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('url', 500);
            $table->string('secret', 128);
            $table->jsonb('events');
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at');

            $table->index('active');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('outbox_id');
            $table->uuid('subscription_id');
            $table->string('event_name', 64);
            $table->jsonb('payload');
            $table->string('status', 20);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_response_status')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('scheduled_at');
            $table->timestampTz('delivered_at')->nullable();

            $table->unique(['outbox_id', 'subscription_id'], 'webhook_deliveries_outbox_sub_unique');
            $table->index(['status', 'scheduled_at'], 'webhook_deliveries_due_idx');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('outbox_messages');
    }
};
