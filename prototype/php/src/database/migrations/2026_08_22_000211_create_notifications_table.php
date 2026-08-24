<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->string('type');
            // The recipient is a seller or a customer, named by the morph map
            // in AppServiceProvider rather than by a class string. The columns
            // are written out rather than taken from `morphs()`, which sizes
            // its id column for an autoincrement key.
            $table->string('notifiable_type');
            $table->string('notifiable_id', 30);
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
