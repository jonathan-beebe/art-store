<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_scopes', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('modifier_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('option_value_id', 30)->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Zero rows for a modifier means it shows product-wide.
            $table->unique(['modifier_id', 'option_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_scopes');
    }
};
