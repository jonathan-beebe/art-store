<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('parent_id', 30)->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            // Materialized path, e.g. '/jewelry/rings/bands/', so a browse
            // page reads its ancestors without walking parent_id.
            $table->string('path');
            $table->boolean('browsable')->default(true);
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
