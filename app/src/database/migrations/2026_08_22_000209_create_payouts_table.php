<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('amount_cents');
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->unique(['seller_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
