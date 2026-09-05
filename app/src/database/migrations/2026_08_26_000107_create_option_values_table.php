<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_values', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('axis_id', 30)->constrained('option_axes')->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            // Null for a custom axis, which enumerates its own labels
            // and skips the property-value catalog.
            $table->foreignUlid('property_value_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->integer('surcharge_cents')->default(0);
            // The option's own absolute price (DSGN-002) — set, and read,
            // only when its axis is `standalone`; null on an `add_on` axis's
            // rows, where `surcharge_cents` carries the price difference
            // instead.
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // "At most one default per axis" is the action's rule. SQLite
            // has no partial unique index, so the schema does not enforce
            // it here.
            $table->index('axis_id');
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_values');
    }
};
