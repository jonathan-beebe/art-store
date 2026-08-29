<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_options', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('modifier_id', 30)->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->integer('add_on_price_cents')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('modifier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
    }
};
