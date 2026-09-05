<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This table lives in the analytics store (config/database.php).
     */
    protected $connection = 'analytics';

    /**
     * The migrations ledger lives in the app database, so rebuilding it
     * (`migrate:fresh`, a deleted `database.sqlite`) re-runs every
     * migration, including this one, against an analytics file that may
     * still hold the table from before. `dropIfExists` before `create`
     * makes that safe: a fresh app database means a fresh analytics table.
     */
    public function up(): void
    {
        Schema::connection('analytics')->dropIfExists('page_view_counts');

        Schema::connection('analytics')->create('page_view_counts', function (Blueprint $table): void {
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
        Schema::connection('analytics')->dropIfExists('page_view_counts');
    }
};
