<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;

it('turns every message in a thread into a row, quoting the body and naming the actor', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'title' => 'Nine Owls',
    ]);
    Message::factory()->from($customer)->create([
        'conversation_id' => $conversation->id,
        'body' => 'Is this ready to ship?',
        'sent_at' => $this->moment('2026-08-20 09:00:00'),
    ]);
    Message::factory()->from($seller)->create([
        'conversation_id' => $conversation->id,
        'body' => 'Yes, it ships tomorrow.',
        'sent_at' => $this->moment('2026-08-20 10:00:00'),
    ]);

    $events = (new MessagingSource)->events(FeedScope::forCustomer($seller, $customer));

    expect($events)->toHaveCount(2);

    $fromCustomer = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->quote === 'Is this ready to ship?'));
    $fromSeller = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->quote === 'Yes, it ships tomorrow.'));

    expect($fromCustomer)->toHaveCount(1)
        ->and($fromCustomer[0]->actor)->toBe('Harry Potter')
        ->and($fromCustomer[0]->text)->toBe('wrote in “Nine Owls”')
        ->and($fromCustomer[0]->kind)->toBe(ActivityKind::Messages);

    expect($fromSeller)->toHaveCount(1)
        ->and($fromSeller[0]->actor)->toBe('You')
        ->and($fromSeller[0]->text)->toBe('replied in “Nine Owls”');
});

it('an order scope keeps the thread about that fulfillment and one about its listing, dropping an unrelated listing thread', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Hermione Granger']);
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $unrelatedListing = $this->listing($seller, ['title' => 'Copper Cauldron Bowl']);

    $order = $this->orderFor($customer, $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();

    $fulfillmentThread = Conversation::factory()
        ->forSubject(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id))
        ->create();
    Message::factory()->from($customer)->create(['conversation_id' => $fulfillmentThread->id, 'body' => 'Where is my order?']);

    $listingThread = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listing->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $listingThread->id, 'body' => 'Does it come framed?']);

    $unrelatedThread = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $unrelatedListing->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $unrelatedThread->id, 'body' => 'Is the bowl dishwasher safe?']);

    $events = (new MessagingSource)->events(FeedScope::forFulfillment($fulfillment));

    expect($events)->toHaveCount(2);

    $quotes = array_map(fn (FeedEvent $event): ?string => $event->quote, $events);

    expect($quotes)->toContain('Where is my order?')
        ->and($quotes)->toContain('Does it come framed?')
        ->and($quotes)->not->toContain('Is the bowl dishwasher safe?');
});

it('a customer scope keeps every thread between the seller and the customer', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $listingOne = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $listingTwo = $this->listing($seller, ['title' => 'Copper Cauldron Bowl']);

    $threadOne = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listingOne->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $threadOne->id, 'body' => 'Is it in stock?']);

    $threadTwo = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
        'listing_id' => $listingTwo->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $threadTwo->id, 'body' => 'How heavy is the bowl?']);

    $events = (new MessagingSource)->events(FeedScope::forCustomer($seller, $customer));

    expect($events)->toHaveCount(2);
});

it('leaves out a thread with another customer', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $otherCustomer = Customer::factory()->create(['name' => 'Ginny Weasley']);

    $thread = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    Message::factory()->from($customer)->create(['conversation_id' => $thread->id, 'body' => 'A question.']);

    $otherThread = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $otherCustomer->id,
    ]);
    Message::factory()->from($otherCustomer)->create(['conversation_id' => $otherThread->id, 'body' => 'A different question.']);

    $events = (new MessagingSource)->events(FeedScope::forCustomer($seller, $customer));

    expect($events)->toHaveCount(1)
        ->and($events[0]->quote)->toBe('A question.');
});
