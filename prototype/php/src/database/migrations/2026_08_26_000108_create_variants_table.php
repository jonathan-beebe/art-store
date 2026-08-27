<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            // Sorted option-value ids, '/'-joined; '' for an axis-free listing
            // (at most one such row — the legacy, single-variant path).
            $table->string('combo_key')->default('');
            $table->integer('price_override_cents')->nullable();
            // Null: serialized (derived from its units) or otherwise uncapped.
            $table->unsignedInteger('quantity')->nullable();
            $table->boolean('is_serialized')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['listing_id', 'combo_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
