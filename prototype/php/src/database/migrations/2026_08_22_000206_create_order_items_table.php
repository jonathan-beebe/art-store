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
            // Nullable: a legacy zero-axis line selects neither. Kept as live
            // references (which unit or variant this line claimed) rather
            // than frozen data, so cancel/decline can restore the right row —
            // the JSON columns below are the frozen half.
            $table->foreignUlid('variant_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('unit_id', 30)->nullable()->constrained()->nullOnDelete();
            // Configuration/answers/price breakdown, snapshotted once at
            // placement (FEAT-028) — never re-derived from the listing's
            // current configurator rows afterward, the same doctrine as
            // `title`/`unit_price_cents` above.
            $table->text('configuration_json')->nullable();
            $table->text('answers_json')->nullable();
            $table->text('price_breakdown_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
