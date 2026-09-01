<?php

declare(strict_types=1);

use App\Domain\Orders\FulfillmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('order_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('status')->default(FulfillmentStatus::AwaitingShipment->value);
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('fee_cents');
            $table->unsignedInteger('net_cents');
            $table->timestamps();

            $table->unique(['order_id', 'seller_id']);
            $table->index(['seller_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
