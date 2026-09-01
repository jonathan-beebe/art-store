<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('description_sections', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('kind');
            $table->string('title')->nullable();
            $table->text('body_md')->nullable();
            $table->json('body_json')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'position']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('description_sections');
    }
};
