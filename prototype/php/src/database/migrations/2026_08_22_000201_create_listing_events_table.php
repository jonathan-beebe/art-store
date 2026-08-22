<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
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
