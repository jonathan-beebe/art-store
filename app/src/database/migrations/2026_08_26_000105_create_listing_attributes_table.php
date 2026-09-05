<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_attributes', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('property_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('property_value_id', 30)->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A property allowed to hold more than one value on a listing
            // (`category_properties.multivalued`) is one row per value, so
            // uniqueness spans all three columns.
            $table->unique(['listing_id', 'property_id', 'property_value_id'], 'listing_attributes_unique');
            $table->index('listing_id');
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_attributes');
    }
};
