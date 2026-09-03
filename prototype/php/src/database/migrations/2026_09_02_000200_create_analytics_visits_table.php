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
        Schema::connection('analytics')->dropIfExists('analytics_visits');

        Schema::connection('analytics')->create('analytics_visits', function (Blueprint $table): void {
            // The `sid` cookie's value is the row's own key: one visit per
            // session cookie, first touch wins.
            $table->string('session_id', 30)->primary();
            $table->timestamp('first_seen_at');
            $table->string('landing_path');
            $table->string('referrer_host')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            // References e.g. customers.id, no FK — the same cross-connection
            // reasoning analytics_events.actor_id already carries.
            $table->string('actor_id', 30)->nullable();

            $table->index('first_seen_at');
            $table->index(['utm_source', 'utm_medium']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_visits');
    }
};
