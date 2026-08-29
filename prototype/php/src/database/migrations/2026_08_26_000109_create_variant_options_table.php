<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_options', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('variant_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('axis_id', 30)->constrained('option_axes')->cascadeOnDelete();
            $table->foreignUlid('option_value_id', 30)->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One value per axis per variant.
            $table->unique(['variant_id', 'axis_id']);
            $table->index('option_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_options');
    }
};
