<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_properties', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('category_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('property_id', 30)->constrained()->cascadeOnDelete();
            $table->boolean('usable_as_attribute')->default(true);
            $table->boolean('usable_as_axis')->default(false);
            $table->boolean('required')->default(false);
            $table->boolean('multivalued')->default(false);
            $table->timestamps();

            $table->unique(['category_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_properties');
    }
};
