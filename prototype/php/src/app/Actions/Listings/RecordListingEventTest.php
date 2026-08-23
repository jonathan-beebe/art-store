<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingEventType;
use Tests\CommerceTestCase;

final class RecordListingEventTest extends CommerceTestCase
{
    public function test_it_records_a_view_against_the_listing_and_the_customer(): void
    {
        $listing = $this->listing($this->seller());
        $customer = $this->verifiedCustomer();

        $event = app(RecordListingEvent::class)($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

        $this->assertSame($listing->id, $event->listing_id);
        $this->assertSame($customer->id, $event->customer_id);
        $this->assertSame(ListingEventType::View, $event->type);
        $this->assertSame('2026-08-20 09:00:00', $event->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_it_records_an_event_with_no_customer_behind_it(): void
    {
        $listing = $this->listing($this->seller());

        $event = app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

        $this->assertNull($event->customer_id);
    }

    public function test_the_listing_counts_the_events_it_has_collected(): void
    {
        $listing = $this->listing($this->seller());
        $customer = $this->verifiedCustomer();
        $record = app(RecordListingEvent::class);
        $now = $this->moment('2026-08-20 09:00:00');

        $record($listing, $customer->id, ListingEventType::View, $now);
        $record($listing, $customer->id, ListingEventType::View, $now);
        $record($listing, $customer->id, ListingEventType::Favorite, $now);
        $record($listing, $customer->id, ListingEventType::CartAdd, $now);

        $counted = $listing->newQuery()->withEventCounts()->find($listing->id);

        $this->assertSame(2, $counted->views_count);
        $this->assertSame(1, $counted->favorites_count);
        $this->assertSame(1, $counted->cart_adds_count);
    }
}
