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
        Schema::connection('analytics')->dropIfExists('analytics_events');

        Schema::connection('analytics')->create('analytics_events', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->timestamp('occurred_at');
            // subject_type/subject_id name the row an event is about (a
            // listing, today) by id only: a foreign key can't reach across
            // the analytics and commerce connections' separate SQLite files.
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 30)->nullable();
            $table->string('actor_id', 30)->nullable();
            // The request behind the event, filled in by
            // App\Analytics\Analytics::recordEvent() from
            // App\Analytics\RequestFacts — null for a CLI run. Long enough
            // for an IPv6 address; the request id itself lives in `data`,
            // never its own column, since nothing filters on it.
            $table->string('ip', 45)->nullable();
            $table->string('session_id')->nullable();
            // A collapsed view's insert dedupes on this — see
            // App\Domain\Listings\ListingViewCollapse::dedupeKey().
            $table->string('dedupe_key')->nullable()->unique();
            $table->text('data');

            $table->index(['subject_id', 'name']);
            $table->index(['name', 'occurred_at']);
            $table->index('actor_id');
            $table->index('ip');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::connection('analytics')->dropIfExists('analytics_events');
    }
};
