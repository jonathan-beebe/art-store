<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Message;

it('clears only the messages unread for the given reader', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $fromCustomer = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    $fromSeller = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);

    app(MarkConversationRead::class)($conversation, $seller, $this->moment('2026-08-20 10:00:00'));

    expect($fromCustomer->fresh()?->read_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00')
        ->and($fromSeller->fresh()?->read_at)->toBeNull();
});

it('leaves an already-read message untouched', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $message = Message::factory()->from($customer)->read()->create(['conversation_id' => $conversation->id]);
    $readAt = $message->read_at;

    app(MarkConversationRead::class)($conversation, $seller, $this->moment('2026-08-20 10:00:00'));

    expect($message->fresh()?->read_at)->toEqual($readAt);
});

it('reads a desk thread for the whole desk, not just the admin who opened it', function (): void {
    $firstAdmin = Admin::factory()->create();
    $secondAdmin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);

    app(MarkConversationRead::class)($conversation, $firstAdmin, $this->moment('2026-08-20 10:00:00'));

    expect($message->fresh()?->read_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
    expect(Message::query()->unreadBy($secondAdmin)->where('id', $message->id)->exists())->toBeFalse();
});
