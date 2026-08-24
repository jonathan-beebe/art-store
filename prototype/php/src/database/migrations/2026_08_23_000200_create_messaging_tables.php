<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->string('kind');
            // Uniquely names what a thread is about. A composite unique index
            // over the five nullable id columns below would not stop a
            // duplicate row, because SQL treats null as distinct from null.
            $table->string('subject_key');
            $table->foreignUlid('seller_id', 30)->nullable()->constrained();
            $table->foreignUlid('customer_id', 30)->nullable()->constrained();
            $table->foreignUlid('admin_id', 30)->nullable()->constrained();
            $table->foreignUlid('listing_id', 30)->nullable()->constrained();
            $table->foreignUlid('fulfillment_id', 30)->nullable()->constrained();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique('subject_key', 'conversations_subject_key_unique');
            $table->index(['seller_id', 'last_message_at'], 'conversations_seller_inbox_index');
            $table->index(['customer_id', 'last_message_at'], 'conversations_customer_inbox_index');
            $table->index(['admin_id', 'last_message_at'], 'conversations_admin_inbox_index');
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('conversation_id', 30)->constrained()->cascadeOnDelete();
            // The morph alias (seller / customer / admin) AppServiceProvider
            // enforces, not a class string.
            $table->string('sender_type');
            $table->string('sender_id', 30);
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // A thread reads in the order it was sent, so the index that
            // serves it is keyed by the instant rather than by the id.
            $table->index(['conversation_id', 'sent_at'], 'messages_thread_index');
            $table->index(['conversation_id', 'read_at'], 'messages_unread_index');
        });

        Schema::create('listing_faqs', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->foreignUlid('source_message_id', 30)->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['listing_id', 'created_at'], 'listing_faqs_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_faqs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
