<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chains', function (Blueprint $table): void {
            $table->string('id', 64)->primary();          // ChainId
            $table->string('name', 100);
            $table->string('family', 16);                 // bitcoin|evm|tron
            $table->string('currency_symbol', 10);
            $table->unsignedSmallInteger('currency_decimals');
            $table->unsignedSmallInteger('required_confirmations');
            $table->unsignedSmallInteger('max_reorg_depth');
            $table->boolean('enabled')->default(false);
            $table->timestampTz('registered_at');
            $table->timestampsTz();

            $table->index(['family', 'enabled']);
        });

        Schema::create('chain_rpc_endpoints', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('chain_id', 64);
            $table->string('url', 500);
            $table->string('kind', 8);                     // http|ws
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestampsTz();

            $table->foreign('chain_id')->references('id')->on('chains')->cascadeOnDelete();
            $table->unique(['chain_id', 'url'], 'chain_rpc_endpoints_chain_url_unique');
            $table->index(['chain_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_rpc_endpoints');
        Schema::dropIfExists('chains');
    }
};
