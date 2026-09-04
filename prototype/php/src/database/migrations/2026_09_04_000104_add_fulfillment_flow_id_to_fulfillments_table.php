<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillments', function (Blueprint $table): void {
            // The flow this parcel started under, stamped at placement.
            // Null on a row from before this column existed; the reader
            // falls back to live resolution for those.
            $table->foreignUlid('fulfillment_flow_id', 30)->nullable()->after('seller_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fulfillments', function (Blueprint $table): void {
            $table->dropForeign(['fulfillment_flow_id']);
            $table->dropColumn('fulfillment_flow_id');
        });
    }
};
