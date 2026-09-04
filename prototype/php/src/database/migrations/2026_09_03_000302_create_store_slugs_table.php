<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_slugs', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('store_profile_id', 30)->constrained()->cascadeOnDelete();
            // Unique across every store, current and retired alike, so a
            // rename can never hand a buyer an address two stores answer.
            $table->string('slug')->unique();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index('retired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_slugs');
    }
};
