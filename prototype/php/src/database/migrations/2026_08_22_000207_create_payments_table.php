<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('amount_cents');
            $table->string('card_last_four');
            $table->string('decline_reason')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['order_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
