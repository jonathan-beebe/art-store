<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('status');
            $table->string('shipping_name');
            $table->string('shipping_line1');
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_region');
            $table->string('shipping_postal_code');
            $table->string('shipping_country');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('total_cents');
            $table->timestamp('placed_at');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
