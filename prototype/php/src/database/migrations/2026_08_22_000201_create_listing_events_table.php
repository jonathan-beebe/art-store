<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_events', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['listing_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_events');
    }
};
