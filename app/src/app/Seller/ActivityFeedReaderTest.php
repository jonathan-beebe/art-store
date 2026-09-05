<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
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

it('merges whatever sources it is given', function (): void {
    $scope = FeedScope::forCustomer($this->seller(), $this->verifiedCustomer());

    $older = new FeedEvent($this->moment('2026-08-01 09:00:00'), ActivityKind::Browse, FeedIcon::Eye, 'Luna', 'viewed a piece');
    $newer = new FeedEvent($this->moment('2026-08-02 09:00:00'), ActivityKind::Order, FeedIcon::Cash, 'Luna', 'placed an order');

    $sourceOfOlder = new class($older) implements ActivityFeedSource
    {
        public function __construct(private readonly FeedEvent $event) {}

        public function events(FeedScope $scope): array
        {
            return [$this->event];
        }
    };

    $sourceOfNewer = new class($newer) implements ActivityFeedSource
    {
        public function __construct(private readonly FeedEvent $event) {}

        public function events(FeedScope $scope): array
        {
            return [$this->event];
        }
    };

    $feed = (new ActivityFeedReader($sourceOfOlder, $sourceOfNewer))->read($scope);

    expect($feed->events)->toBe([$newer, $older]);
});
