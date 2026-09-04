<?php

declare(strict_types=1);

namespace App\Models;

it('is unread for the participant who did not send it', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);

    expect(Message::query()->unreadBy($seller)->count())->toBe(1)
        ->and(Message::query()->unreadBy($customer)->count())->toBe(0);
});

it('is not unread once it has been read', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->read()->create(['conversation_id' => $conversation->id]);

    expect(Message::query()->unreadBy($seller)->count())->toBe(0);
});

it('counts toward an inbox only across the readers own threads', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $mine = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $mine->id]);
    $betweenTwoOtherPeople = Conversation::factory()->listingQuestion()->create();
    Message::factory()->unread()->create(['conversation_id' => $betweenTwoOtherPeople->id]);

    expect(Message::query()->unreadInInboxOf($seller)->count())->toBe(1)
        ->and(Message::query()->unreadInInboxOf($customer)->count())->toBe(0);
});

it('reads its sender through the morph map', function (): void {
    $seller = $this->seller();
    $message = Message::factory()->from($seller)->create();

    expect($message->sender->is($seller))->toBeTrue();
});

it('names its sender', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $message = Message::factory()->from($seller)->create();

    expect($message->senderName())->toBe('Blue Kiln Studio');
});

it('names the message it replies to', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();
    $question = Message::factory()->create(['conversation_id' => $conversation->id]);
    $reply = Message::factory()->replyingTo($question)->create();

    expect($reply->replyTo?->is($question))->toBeTrue();
});

it('leaves a reply intact when the message it quoted is removed', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();
    $question = Message::factory()->create(['conversation_id' => $conversation->id]);
    $reply = Message::factory()->replyingTo($question)->create();

    $question->delete();

    expect($reply->fresh()?->reply_to_message_id)->toBeNull();
});

it('treats every admin as one desk: a message from any admin is never unread for any admin', function (): void {
    $firstAdmin = Admin::factory()->create();
    $secondAdmin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create();
    Message::factory()->from($firstAdmin)->create(['conversation_id' => $conversation->id]);

    expect(Message::query()->unreadBy($secondAdmin)->count())->toBe(0);
});

it('is unread for the desk when the other side sent it', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
    Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);

    expect(Message::query()->unreadBy($admin)->count())->toBe(1);
});
