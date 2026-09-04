<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_flows', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['seller_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_flows');
    }
};
