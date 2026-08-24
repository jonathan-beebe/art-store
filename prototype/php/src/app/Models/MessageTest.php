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
