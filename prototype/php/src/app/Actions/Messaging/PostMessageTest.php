<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\MessageBody;
use App\Models\Conversation;

it('appends a message to the thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);

    $message = app(PostMessage::class)($conversation, $customer, MessageBody::of('Is this still available?'), $this->moment('2026-08-20 10:00:00'));

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->body)->toBe('Is this still available?')
        ->and($message->sender_type)->toBe('customer')
        ->and($message->sender_id)->toBe($customer->id)
        ->and($message->sent_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00')
        ->and($message->read_at)->toBeNull();
});

it('moves the thread to the top of both inboxes', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'last_message_at' => $this->moment('2026-08-01 09:00:00'),
    ]);

    app(PostMessage::class)($conversation, $customer, MessageBody::of('Is this still available?'), $this->moment('2026-08-20 10:00:00'));

    expect($conversation->fresh()?->last_message_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
});
