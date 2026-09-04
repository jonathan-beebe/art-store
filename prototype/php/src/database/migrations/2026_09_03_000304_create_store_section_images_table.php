<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_section_images', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('store_section_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_image_id', 30)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['store_section_id', 'position']);
            $table->unique(['store_section_id', 'store_image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_section_images');
    }
};
