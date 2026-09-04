<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_sections', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('store_profile_id', 30)->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedInteger('position');
            $table->string('heading')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['store_profile_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sections');
    }
};
