<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('fulfillment_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('payout_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->integer('amount_cents');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['seller_id', 'type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
