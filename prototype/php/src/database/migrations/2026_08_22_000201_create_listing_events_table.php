<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This table lives in the analytics store (config/database.php), not
     * the database the migrations ledger itself belongs to.
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
        Schema::connection('analytics')->dropIfExists('listing_events');

        Schema::connection('analytics')->create('listing_events', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            // listing_id, seller_id, and customer_id name rows in the app
            // database by id only: a foreign key can't reach across the
            // analytics and commerce connections' separate SQLite files.
            $table->string('listing_id', 30);
            $table->string('seller_id', 30);
            $table->string('customer_id', 30)->nullable();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['listing_id', 'type']);
            $table->index(['seller_id', 'occurred_at']);
            // MergeAnonymousCustomer queries by customer_id to re-point
            // rows when an anonymous identity folds into a verified one.
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::connection('analytics')->dropIfExists('listing_events');
    }
};
