<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\PageViewSite;

it('renders 200 with the tiles and the by-listing breakdown', function (): void {
    $seller = $this->seller('Weasley Wireless');
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk']);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "f{$i}"));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.favorite?range=7&by=listing');

    $response->assertOk();
    $response->assertSee('Favorites')
        ->assertSee('listing.favorite')
        ->assertSee('This range')
        ->assertSee('Busiest day')
        ->assertSee('By listing')
        ->assertSeeInOrder(['The Burrow at Dusk', 'Weasley Wireless', '3']);
});

it('renders 200 with the by-actor breakdown', function (): void {
    $listing = $this->listing($this->seller());
    $hermione = $this->verifiedCustomer();
    $hermione->update(['email' => 'hermione@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.favorite?range=7&by=actor');

    $response->assertOk();
    $response->assertSee('By actor')
        ->assertSee('verified')
        ->assertSee('hermione@example.com');
});

it('renders 200 for page.view, always by pattern', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-19 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-19 15:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/page.view?range=7');

    $response->assertOk();
    $response->assertSee('Page views')
        ->assertSee('By pattern')
        ->assertSee('shop')
        ->assertSee('/art/{listing}');
});

it('shows page views by route pattern, busiest first, with the recorded counts', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 15:00:00'));
    $analytics->recordPageView(PageViewSite::Seller, '/seller', $this->moment('2026-08-20 09:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/page.view?range=7');

    $response->assertOk()
        ->assertSeeInOrder(['shop', '/art/{listing}', '2', 'seller', '/seller', '1']);
});

it('carries range through the by links', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.view?range=7');

    $response->assertOk();
    expect($response->getContent())->toMatch('#/admin/analytics/events/listing\.view\?[^"]*range=7[^"]*by=listing|/admin/analytics/events/listing\.view\?[^"]*by=listing[^"]*range=7#');
});

it('answers 400 for a breakdown the event does not offer', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.view?by=pattern');

    $response->assertStatus(400);
});

it('answers 400 for a nonsense breakdown value', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.view?by=nonsense');

    $response->assertStatus(400);
});

it('answers 404 for a name that is neither an event nor page.view', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/not-a-real-event');

    $response->assertNotFound();
});

it('renders the by-listing breakdown on a fixed number of queries however many listings the range carries', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 8) as $i) {
        $listing = $this->listing($seller, ['title' => "Listing {$i}"]);
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), "f{$i}"));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(4, 'sqlite')
        ->expectsDatabaseQueryCount(5, 'analytics')
        ->get('/admin/analytics/events/listing.favorite?range=7&by=listing');

    $response->assertOk();
});

it('shows a deleted listing as gone, still carrying its id', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();
    $listingId = $listing->id;
    $listing->delete();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.favorite?range=7&by=listing');

    $response->assertOk();
    $response->assertSee('listing no longer exists');
    expect($response->getContent())->toContain($listingId);
});
