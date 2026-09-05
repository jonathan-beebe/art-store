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
            // A seller or a customer types this opening a support thread; a
            // listing question derives it from the question. A fulfillment
            // thread carries none — it is named by its order everywhere it
            // appears.
            $table->string('title')->nullable();
            // Uniquely names what a fulfillment thread is about — reused by
            // this key on every later look-up, never opened fresh again. A
            // composite unique index over the nullable id columns below
            // would not stop a duplicate row,
            // because SQL treats null as distinct from null; the other three
            // kinds leave this null, which the unique index ignores.
            $table->string('subject_key')->nullable();
            $table->foreignUlid('seller_id', 30)->nullable()->constrained();
            $table->foreignUlid('customer_id', 30)->nullable()->constrained();
            // Who first answered on a desk thread ("handled by"): every
            // admin is the desk, collectively, on the two support kinds.
            // Null until an admin replies.
            $table->foreignUlid('admin_id', 30)->nullable()->constrained();
            $table->foreignUlid('listing_id', 30)->nullable()->constrained();
            $table->foreignUlid('fulfillment_id', 30)->nullable()->constrained();
            $table->foreignUlid('order_id', 30)->nullable()->constrained();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_type')->nullable();
            $table->string('resolved_by_id', 30)->nullable();
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
            // The morph alias (seller / customer / admin) AppServiceProvider's
            // morph map enforces.
            $table->string('sender_type');
            $table->string('sender_id', 30);
            // The message this one answers, when the reader followed a
            // "Reply" link. A quoted message that is later removed leaves
            // its replies intact.
            $table->foreignUlid('reply_to_message_id', 30)->nullable()->constrained('messages')->nullOnDelete();
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // A thread reads in the order it was sent, so the index that
            // serves it is keyed by the instant.
            $table->index(['conversation_id', 'sent_at'], 'messages_thread_index');
            $table->index(['conversation_id', 'read_at'], 'messages_unread_index');
        });

        Schema::create('listing_faqs', function (Blueprint $table): void {
            $table->string('id', 30)->primary();
            $table->foreignUlid('listing_id', 30)->constrained()->cascadeOnDelete();
            $table->foreignUlid('seller_id', 30)->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->foreignUlid('source_message_id', 30)->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['listing_id', 'created_at'], 'listing_faqs_listing_index');
            $table->index('seller_id', 'listing_faqs_seller_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_faqs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
