<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A row here is the marker `php artisan seed:activity` checks before it
     * runs: one row means the command has already filled the store with a
     * ramp of activity, so a second run refuses to add another.
     */
    public function up(): void
    {
        Schema::create('seed_runs', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->integer('seed');
            $table->unsignedSmallInteger('day_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seed_runs');
    }
};
