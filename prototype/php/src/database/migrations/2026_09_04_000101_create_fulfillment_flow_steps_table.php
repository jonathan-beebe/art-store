<?php

declare(strict_types=1);

use App\Domain\Fulfillment\FlowStepAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_flow_steps', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('fulfillment_flow_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label');
            $table->string('action')->default(FlowStepAction::None->value);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['fulfillment_flow_id', 'key']);
            $table->unique(['fulfillment_flow_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_flow_steps');
    }
};
