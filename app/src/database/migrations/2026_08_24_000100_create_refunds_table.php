<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('order_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('fulfillment_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('payment_id', 30)->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 500);
            // Who sent the money back: the seller who declined the parcel or
            // the admin who settled a dispute, named the way every other
            // actor column is (docs/spec.md §1).
            $table->string('issued_by_type');
            $table->string('issued_by_id', 30);
            $table->timestamps();

            // One refund per fulfillment: the amount is always the whole
            // subtotal, so a second one would be a second full refund.
            $table->unique('fulfillment_id');
            $table->index(['order_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
