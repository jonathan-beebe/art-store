<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            // Null means the seller's default flow ships this piece.
            $table->foreignUlid('fulfillment_flow_id', 30)->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropForeign(['fulfillment_flow_id']);
            $table->dropColumn('fulfillment_flow_id');
        });
    }
};
