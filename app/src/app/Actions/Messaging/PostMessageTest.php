<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\DomainRuleViolation;
use App\Domain\Messaging\MessageBody;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Message;

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
        ->and($message->read_at)->toBeNull()
        ->and($message->reply_to_message_id)->toBeNull();
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

it('names the message it replies to', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    $question = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);

    $answer = app(PostMessage::class)($conversation, $seller, MessageBody::of('Yes, still available.'), $this->moment('2026-08-20 10:00:00'), $question);

    expect($answer->reply_to_message_id)->toBe($question->id);
});

it('refuses a reply to a message from another thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    $elsewhere = Message::factory()->create();

    $post = fn () => app(PostMessage::class)($conversation, $seller, MessageBody::of('Yes.'), $this->moment('2026-08-20 10:00:00'), $elsewhere);

    expect($post)->toThrow(DomainRuleViolation::class, 'A reply must belong to the same thread.')
        ->and(Message::where('conversation_id', $conversation->id)->count())->toBe(0);
});

it('stamps admin_id on a desk thread\'s first admin reply, and leaves it alone after', function (): void {
    $firstAdmin = Admin::factory()->create();
    $secondAdmin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create();

    app(PostMessage::class)($conversation, $firstAdmin, MessageBody::of('Looking into it.'), $this->moment('2026-08-20 10:00:00'));
    app(PostMessage::class)($conversation, $secondAdmin, MessageBody::of('Confirmed.'), $this->moment('2026-08-20 11:00:00'));

    expect($conversation->fresh()?->admin_id)->toBe($firstAdmin->id);
});

it('leaves admin_id alone on a non-desk thread', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    app(PostMessage::class)($conversation, $seller, MessageBody::of('Reply.'), $this->moment('2026-08-20 10:00:00'));

    expect($conversation->fresh()?->admin_id)->toBeNull();
});

it('reopens a resolved thread when the supported side posts', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'resolved_at' => $this->moment('2026-08-19 09:00:00'),
        'resolved_by_type' => 'seller',
        'resolved_by_id' => $seller->id,
    ]);

    app(PostMessage::class)($conversation, $customer, MessageBody::of('Actually, one more thing.'), $this->moment('2026-08-20 10:00:00'));

    $fresh = $conversation->fresh();
    expect($fresh?->resolved_at)->toBeNull()
        ->and($fresh?->resolved_by_type)->toBeNull()
        ->and($fresh?->resolved_by_id)->toBeNull();
});

it('leaves a resolved thread resolved when the supporting side posts again', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-19 09:00:00'),
        'resolved_by_type' => 'seller',
        'resolved_by_id' => $seller->id,
    ]);

    app(PostMessage::class)($conversation, $seller, MessageBody::of('Glad I could help.'), $this->moment('2026-08-20 10:00:00'));

    expect($conversation->fresh()?->resolved_at)->not->toBeNull();
});

it('leaves an open thread open regardless of who posts', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);

    app(PostMessage::class)($conversation, $seller, MessageBody::of('Following up.'), $this->moment('2026-08-20 10:00:00'));

    expect($conversation->fresh()?->resolved_at)->toBeNull();
});
