<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_view_counts', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->string('site', 20);
            // A route's pattern (`admin/orders/{order}`), not the concrete
            // URL, so a thousand listing pages share one row.
            $table->string('path_pattern');
            $table->date('day');
            $table->unsignedInteger('count')->default(1);
            $table->timestamps();

            // What makes the roll-up one upsert: the first hit of a day
            // inserts, every later one updates this same row.
            $table->unique(['site', 'path_pattern', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_counts');
    }
};
