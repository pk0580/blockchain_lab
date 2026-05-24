<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hd_seeds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 64)->unique();
            $table->string('family', 16)->nullable();    // null = multi-family
            $table->timestampTz('created_at');

            $table->index('family');
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('hd_seed_id');
            $table->string('family', 16);
            $table->string('address', 96);
            $table->string('derivation_path', 64);
            $table->unsignedInteger('derivation_index');
            $table->uuid('wallet_id')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('hd_seed_id')->references('id')->on('hd_seeds')->cascadeOnDelete();
            $table->unique(['family', 'address'], 'addresses_family_address_unique');
            $table->unique(
                ['hd_seed_id', 'family', 'derivation_index'],
                'addresses_seed_family_index_unique',
            );
            $table->index(['wallet_id']);
            $table->index(['family', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('hd_seeds');
    }
};
