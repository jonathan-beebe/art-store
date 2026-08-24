<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_merges', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('anonymous_customer_id', 30)->unique()->constrained('customers')->cascadeOnDelete();
            $table->foreignUlid('customer_id', 30)->constrained('customers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_merges');
    }
};
