<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_profiles', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('seller_id', 30)->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('location')->nullable();
            // `store_images` carries `store_profile_id`, so a foreign key
            // back the other way would be a cycle SQLite cannot create in
            // either order. The two columns hold a `sim_` id and are
            // cleared by the image's own delete path.
            $table->string('portrait_image_id', 30)->nullable();
            $table->string('cover_image_id', 30)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('portrait_image_id');
            $table->index('cover_image_id');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_profiles');
    }
};
