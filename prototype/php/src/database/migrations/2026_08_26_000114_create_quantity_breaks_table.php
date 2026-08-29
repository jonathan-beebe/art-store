<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quantity_breaks', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');
            $table->unsignedInteger('discount_bps');
            $table->timestamps();

            $table->unique(['listing_id', 'min_qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quantity_breaks');
    }
};
