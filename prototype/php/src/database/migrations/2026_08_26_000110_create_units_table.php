<?php

declare(strict_types=1);

use App\Domain\Configurator\UnitState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('variant_id', 30)->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('state')->default(UnitState::Available->value);
            $table->text('condition_note')->nullable();
            $table->json('specs_json')->nullable();
            $table->integer('price_override_cents')->nullable();
            $table->timestamps();

            $table->unique(['variant_id', 'label']);
            $table->index(['variant_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
