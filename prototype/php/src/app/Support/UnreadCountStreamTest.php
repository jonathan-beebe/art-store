<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Sleep;

it("sends the actor's own count as the first frame", function (): void {
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();

    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $deadline = now()->addSeconds(UnreadCountStream::LIFETIME_SECONDS)->toDateTimeImmutable();
    $event = UnreadCountStream::forActor($seller, $deadline)->current();

    expect($event)->toBeInstanceOf(StreamedEvent::class)
        ->and($event->event)->toBe('unread')
        ->and($event->data)->toBe(1);
});

it('repeats a steady count on every tick, so every tick reaches the client, and stops at the deadline', function (): void {
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();

    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $deadline = now()->addSeconds(UnreadCountStream::LIFETIME_SECONDS)->toDateTimeImmutable();
    $frames = iterator_to_array(UnreadCountStream::forActor($seller, $deadline), preserve_keys: false);
    $ticks = (int) ceil(UnreadCountStream::LIFETIME_SECONDS / UnreadCountStream::TICK_SECONDS);

    expect($frames)->toHaveCount($ticks)
        ->and(array_map(fn (StreamedEvent $frame): mixed => $frame->data, $frames))->each->toBe(1);
    Sleep::assertSleptTimes($ticks);
});

it('sends the new count on the first tick after it changes mid-stream', function (): void {
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();

    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $deadline = now()->addSeconds(UnreadCountStream::LIFETIME_SECONDS)->toDateTimeImmutable();
    $generator = UnreadCountStream::forActor($seller, $deadline);

    expect($generator->current()->data)->toBe(1);

    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);
    $generator->next();

    expect($generator->valid())->toBeTrue()
        ->and($generator->current()->data)->toBe(2);
});

it("reads each actor's own count, never the other's", function (): void {
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();

    $sellerWithUnread = $this->seller('Studio With Mail');
    $sellerWithNone = $this->seller('Studio Without Mail');
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $sellerWithUnread->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->unread()->create(['conversation_id' => $conversation->id]);

    $deadline = now()->addSeconds(UnreadCountStream::LIFETIME_SECONDS)->toDateTimeImmutable();

    expect(UnreadCountStream::forActor($sellerWithUnread, $deadline)->current()->data)->toBe(1)
        ->and(UnreadCountStream::forActor($sellerWithNone, $deadline)->current()->data)->toBe(0);
});
