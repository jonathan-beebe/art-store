<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifiers', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('prompt');
            $table->text('instructions')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('position')->default(0);
            // text/measurement price the answer itself; select prices per
            // chosen option instead (see modifier_options.add_on_price_cents).
            $table->integer('add_on_price_cents')->default(0);
            // text
            $table->unsignedInteger('char_limit')->nullable();
            // measurement
            $table->string('unit')->nullable();
            $table->float('min_value')->nullable();
            $table->float('max_value')->nullable();
            $table->integer('rate_cents_per_unit')->nullable();
            $table->timestamps();

            $table->index('listing_id');
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifiers');
    }
};
