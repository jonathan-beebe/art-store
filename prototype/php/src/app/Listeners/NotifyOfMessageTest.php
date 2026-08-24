<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\MessageReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

it('tells the participant who did not send the message', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $message = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo($seller, MessageReceived::class);
    Notification::assertNotSentTo($customer, MessageReceived::class);
});

it('notifies nobody when the thread is missing its other side', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => null,
    ]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertNothingSent();
});

it('names the listing as the topic of a listing question', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller, ['title' => 'Blue Vase']);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listing->id,
    ]);
    $message = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $seller,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($seller)['body'] === 'You have a new message about Blue Vase.',
    );
});

it('names the order as the topic of a fulfillment thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);
    $conversation = Conversation::factory()->fulfillment()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'fulfillment_id' => $fulfillment->id,
    ]);
    $message = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $seller,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($seller)['body'] === "You have a new message about Order #{$fulfillment->order_id}.",
    );
});

it('names support as the topic of an admin thread', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create([
        'admin_id' => $admin->id,
        'seller_id' => $seller->id,
    ]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $admin,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($admin)['body'] === 'You have a new message about Support.',
    );
});

it('links to the admin thread once that site registers its route', function (): void {
    expect(Route::has('admin.messages.show'))->toBeTrue();
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create([
        'admin_id' => $admin->id,
        'seller_id' => $seller->id,
    ]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $admin,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($admin)['url'] === route('admin.messages.show', $conversation),
    );
});

it('links to the thread on the recipient site once its route exists', function (): void {
    expect(Route::has('seller.messages.show'))->toBeTrue();
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $message = Message::factory()->from($customer)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $seller,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($seller)['url'] === route('seller.messages.show', $conversation),
    );
});

it('links to the storefront thread once that site registers its route', function (): void {
    expect(Route::has('shop.messages.show'))->toBeTrue();
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $customer,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($customer)['url'] === route('shop.messages.show', $conversation),
    );
});
