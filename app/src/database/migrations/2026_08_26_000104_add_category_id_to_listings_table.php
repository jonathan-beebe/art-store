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
            // Nullable: zero rows added keeps every existing listing behaving
            // exactly as it does today, the legacy path FEAT-025 must not
            // disturb.
            $table->foreignUlid('category_id', 30)->nullable()->after('seller_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
