<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('cart_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            // Nullable: a legacy zero-axis listing selects neither. SQLite
            // defers foreign-key validation, so referencing `variants`/`units`
            // (created by later migrations) here is safe.
            $table->foreignUlid('variant_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('unit_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            // Axis/value ids and labels the buyer chose, snapshotted at add
            // time so the cart page renders even if a variant later vanishes.
            $table->text('configuration_json')->nullable();
            // Modifier id => {prompt, answer}, for display.
            $table->text('answers_json')->nullable();
            // CartLineFingerprint::of(variant_id, unit_id, answers) — constant
            // for a legacy zero-axis listing, so its one-click add and
            // merge-on-duplicate-add behavior is unchanged.
            $table->string('fingerprint');
            $table->timestamps();

            $table->unique(['cart_id', 'listing_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
