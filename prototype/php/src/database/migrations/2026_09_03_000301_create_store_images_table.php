<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_images', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('store_profile_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->timestamps();

            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_images');
    }
};
