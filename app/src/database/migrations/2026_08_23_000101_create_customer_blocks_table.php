<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_blocks', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('customer_id', 30)->constrained()->cascadeOnDelete();
            $table->string('reason');
            // "At most one active block" is the action's rule. SQLite has no
            // partial unique index, so the schema does not enforce it here.
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_blocks');
    }
};
