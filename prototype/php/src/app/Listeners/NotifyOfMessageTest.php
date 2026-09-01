<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\MessageReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use LogicException;

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
        fn (MessageReceived $notification): bool => $notification->toArray($seller)['body'] === "You have a new message about Order {$fulfillment->order_id}.",
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

it('links to the thread on the recipient site once its route exists', function (string $kind, string $senderKey, string $recipientKey, string $routeName): void {
    expect(Route::has($routeName))->toBeTrue();

    $participants = [
        'admin' => $this->admin(),
        'seller' => $this->seller(),
        'customer' => $this->verifiedCustomer(),
    ];

    $conversation = match ($kind) {
        'adminSeller' => Conversation::factory()->adminSeller()->create([
            'admin_id' => $participants['admin']->id,
            'seller_id' => $participants['seller']->id,
        ]),
        'listingQuestion' => Conversation::factory()->listingQuestion()->create([
            'seller_id' => $participants['seller']->id,
            'customer_id' => $participants['customer']->id,
        ]),
        default => throw new LogicException("unhandled conversation kind {$kind}"),
    };
    $message = Message::factory()->from($participants[$senderKey])->create(['conversation_id' => $conversation->id]);
    Notification::fake();

    app(NotifyOfMessage::class)->handle(new MessagePosted($message, $this->moment('2026-08-20 10:00:00')));

    $recipient = $participants[$recipientKey];
    Notification::assertSentTo(
        $recipient,
        MessageReceived::class,
        fn (MessageReceived $notification): bool => $notification->toArray($recipient)['url'] === route($routeName, $conversation),
    );
})->with([
    'admin thread' => ['adminSeller', 'seller', 'admin', 'admin.messages.show'],
    'seller thread' => ['listingQuestion', 'customer', 'seller', 'seller.messages.show'],
    'customer thread' => ['listingQuestion', 'seller', 'customer', 'shop.messages.show'],
]);
