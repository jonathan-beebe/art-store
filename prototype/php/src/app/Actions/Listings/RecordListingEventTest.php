<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingEventType;

it('records a view against the listing and the customer', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();

    $event = app(RecordListingEvent::class)($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

    expect($event->listing_id)->toBe($listing->id)
        ->and($event->customer_id)->toBe($customer->id)
        ->and($event->type)->toBe(ListingEventType::View)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('records an event with no customer behind it', function (): void {
    $listing = $this->listing($this->seller());

    $event = app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

    expect($event->customer_id)->toBeNull();
});

it('counts the events a listing has collected', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $record = app(RecordListingEvent::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $record($listing, $customer->id, ListingEventType::View, $now);
    $record($listing, $customer->id, ListingEventType::View, $now);
    $record($listing, $customer->id, ListingEventType::Favorite, $now);
    $record($listing, $customer->id, ListingEventType::CartAdd, $now);

    $counted = $listing->newQuery()->withEventCounts()->findOrFail($listing->id);

    expect($counted->views_count)->toBe(2)
        ->and($counted->favorites_count)->toBe(1)
        ->and($counted->cart_adds_count)->toBe(1);
});
