<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('order_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            // Title and price are snapshots: an order reads the same after the
            // seller edits or deletes the listing behind it.
            $table->string('title');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
