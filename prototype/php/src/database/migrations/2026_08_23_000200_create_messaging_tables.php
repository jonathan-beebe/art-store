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
            $table->id();
            $table->string('kind');
            // Uniquely names what a thread is about. A composite unique index
            // over the five nullable id columns below would not stop a
            // duplicate row, because SQL treats null as distinct from null.
            $table->string('subject_key');
            $table->foreignId('seller_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('admin_id')->nullable()->constrained();
            $table->foreignId('listing_id')->nullable()->constrained();
            $table->foreignId('fulfillment_id')->nullable()->constrained();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique('subject_key', 'conversations_subject_key_unique');
            $table->index(['seller_id', 'last_message_at'], 'conversations_seller_inbox_index');
            $table->index(['customer_id', 'last_message_at'], 'conversations_customer_inbox_index');
            $table->index(['admin_id', 'last_message_at'], 'conversations_admin_inbox_index');
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // The morph alias (seller / customer / admin) AppServiceProvider
            // enforces, not a class string.
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id'], 'messages_thread_index');
            $table->index(['conversation_id', 'read_at'], 'messages_unread_index');
        });

        Schema::create('listing_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->foreignId('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['listing_id', 'id'], 'listing_faqs_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_faqs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
