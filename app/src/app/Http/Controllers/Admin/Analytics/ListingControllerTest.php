<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;

it('shows the funnel below the tiles, counting only this listing\'s own order', function (): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller);
    $listingTwo = $this->listing($seller);
    $this->orderFor($this->verifiedCustomer(), $listingOne, $listingTwo);
    app(Analytics::class)->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.listings.show', $listingOne));

    $response->assertOk();
    $response->assertSeeInOrder(['Busiest day', 'Funnel', 'Visitors', 'Orders placed', 'By day']);
});

it('renders 200 with the listing\'s facts, tiles, and feed', function (): void {
    $seller = $this->seller('Weasley Studio');
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $customer = $this->verifiedCustomer();
    $customer->update(['email' => 'hermione@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.listings.show', $listing));

    $response->assertOk();
    $response->assertSee('The Burrow at Dusk');
    $response->assertSee($listing->id);
    $response->assertSee('Weasley Studio');
    $response->assertSee('hermione@example.com');
    $response->assertSee('Open listing');
    $response->assertDontSee('Open in logs');
});

it('filters the listing feed by event name', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.listings.show', ['listing' => $listing->id, 'event' => 'listing.favorite']));

    $response->assertOk();
    $response->assertSee('1 of 2 shown, newest first');
});

it('answers 400 for an unrecognised event filter on the listing page', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.listings.show', ['listing' => $listing->id, 'event' => 'nonsense']));

    $response->assertStatus(400);
});

it('answers 400 for an unrecognised range on the listing page', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.listings.show', ['listing' => $listing->id, 'range' => '14']));

    $response->assertStatus(400);
});

it('answers not found for an unknown listing id', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/listings/lst_01J5X3M9A2K8YB7Q4R6T1V0WZE');

    $response->assertNotFound();
});

it('names an anonymous actor "Anonymous visitor" on the listing page\'s feed', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.listings.show', $listing));

    $response->assertOk();
    $response->assertSee('Anonymous visitor');
});

it('renders the listing page on a fixed number of queries however many actors its feed holds', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);

    foreach (range(1, 15) as $i) {
        $customer = $this->verifiedCustomer();
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')->modify("+{$i} minutes"), "e{$i}"));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(7, 'sqlite')
        ->expectsDatabaseQueryCount(10, 'analytics')
        ->get(route('admin.analytics.listings.show', $listing));

    $response->assertOk();
});
