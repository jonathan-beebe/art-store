<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_flows', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['seller_id', 'is_default']);
        });

        // One default flow per seller, held by the database rather than by
        // the action that writes it. Blueprint has no partial unique index.
        // The bare column as the predicate is what SQLite, which the app
        // develops and tests on, and Postgres both read as
        // "true" — Postgres has no `boolean = integer`, so `= 1` fails
        // there. MySQL has no partial index at all; this table is not part
        // of its contract.
        DB::statement('create unique index fulfillment_flows_default_unique on fulfillment_flows (seller_id) where is_default');
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_flows');
    }
};
