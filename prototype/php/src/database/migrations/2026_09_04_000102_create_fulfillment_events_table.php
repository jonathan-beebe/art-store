<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_events', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('fulfillment_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->foreignUlid('fulfillment_flow_step_id', 30)->nullable()->constrained()->nullOnDelete();
            // The step's words at the moment it was completed, so the log
            // still reads after the seller removes the step from their flow.
            $table->string('step_label')->nullable();
            $table->string('actor_type');
            $table->string('actor_id', 30)->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // One completion per step per fulfillment. A row with no step —
            // every transition event — is outside the constraint, because a
            // unique index counts each null as its own value.
            $table->unique(['fulfillment_id', 'fulfillment_flow_step_id']);
            $table->index(['fulfillment_id', 'occurred_at']);
            $table->index(['seller_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_events');
    }
};
