<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_axes', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            // Null: a custom, label-only axis with no catalog property behind it.
            $table->foreignUlid('property_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // Chosen once, at creation (DSGN-002): "standalone" prices each
            // option on its own (`option_values.price_cents`); "add_on"
            // (the default, and every axis before this column existed) adds
            // a signed surcharge to the listing's base price.
            $table->string('pricing_mode', 20)->default('add_on');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_axes');
    }
};
