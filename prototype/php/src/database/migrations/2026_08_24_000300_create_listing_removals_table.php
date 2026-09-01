<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_removals', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('reason');
            // "At most one active removal" is the action's rule, not a partial
            // unique index here — SQLite has no partial unique index.
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'lifted_at']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_removals');
    }
};
