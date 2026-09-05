<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('customer_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
