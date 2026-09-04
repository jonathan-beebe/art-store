<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Models\Conversation;
use App\Models\Message;

/**
 * @param  list<FeedEvent>  $events
 * @return list<string>
 */
function readerKinds(array $events): array
{
    return array_map(fn (FeedEvent $event): string => $event->kind->value, $events);
}

it('merges every source into one feed, newest first', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->deliveredFulfillmentFor($seller, $customer);
    $listing = $fulfillment->load('order.items')->order->items->sole()->listing_id;

    $analytics = new Analytics;
    $analytics->recordEvent(AnalyticsEvent::forListing(
        AnalyticsEventName::ListingView,
        $listing,
        $customer->id,
        $this->moment('2026-08-19 09:00:00'),
    ));
    $analytics->flush();

    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id))
        ->create(['last_message_at' => $this->moment('2026-08-25 09:00:00')]);
    Message::factory()->from($customer)->create([
        'conversation_id' => $conversation->id,
        'body' => 'Thank you, it arrived.',
        'sent_at' => $this->moment('2026-08-25 09:00:00'),
    ]);

    $feed = app(ActivityFeedReader::class)->read(FeedScope::forFulfillment($fulfillment));

    $kinds = array_unique(readerKinds($feed->events));
    sort($kinds);

    expect($kinds)->toBe(['browse', 'messages', 'order', 'shipping']);

    $instants = array_map(fn (FeedEvent $event): int => $event->occurredAt->getTimestamp(), $feed->events);
    $sorted = $instants;
    rsort($sorted);

    expect($instants)->toBe($sorted);
});

it('narrows to one kind without changing what the sources returned', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($seller, $customer);

    $feed = app(ActivityFeedReader::class)->read(FeedScope::forFulfillment($fulfillment));
    $shipping = $feed->filter(ActivityKind::Shipping);

    expect($shipping->events)->not->toBeEmpty()
        ->and(array_unique(readerKinds($shipping->events)))->toBe(['shipping'])
        ->and(count($feed->events))->toBeGreaterThan(count($shipping->events))
        ->and($feed->filter(null)->events)->toBe($feed->events);
});

it('reads an empty feed for a customer who has done nothing with this seller', function (): void {
    $feed = app(ActivityFeedReader::class)->read(
        FeedScope::forCustomer($this->seller('Lovegood Curiosities'), $this->verifiedCustomer()),
    );

    expect($feed->isEmpty())->toBeTrue();
});
