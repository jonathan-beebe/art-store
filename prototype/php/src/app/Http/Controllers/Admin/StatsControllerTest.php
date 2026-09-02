<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Favorites\ToggleFavorite;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsEventName;
use App\Domain\Analytics\PageViewSite;

it('renders no list pane — a full-content section, not list+detail', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    $response->assertSee('<main id="main-content" data-layout="full"', escape: false);
});

it('shows page views by day, inside the seven-day window', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 15:00:00'));
    $analytics->recordPageView(PageViewSite::Seller, '/seller', $this->moment('2026-08-20 09:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    expect($response->getContent())->toMatch('/data-day="2026-08-20"[\s\S]*?data-cell="count"[^>]*>3</');
});

it('leaves a day outside the seven-day window out', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-01 09:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    $response->assertDontSee('2026-08-01');
});

it('shows page views by route pattern, busiest first', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 15:00:00'));
    $analytics->recordPageView(PageViewSite::Seller, '/seller', $this->moment('2026-08-20 09:00:00'));
    $analytics->flush();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-pattern="shop \/art\/\{listing\}"[\s\S]*?data-cell="count"[^>]*>2</')
        ->toMatch('/data-pattern="seller \/seller"[\s\S]*?data-cell="count"[^>]*>1</');
});

it('counts listing events by name', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:00:00')));
    app(ToggleFavorite::class)($customer, $listing, $this->moment('2026-08-20 09:00:00'));
    $analytics->flush();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-stat="event-listing\.view"[\s\S]*?>1</')
        ->toMatch('/data-stat="event-listing\.favorite"[\s\S]*?>1</')
        ->toMatch('/data-stat="event-listing\.cart_add"[\s\S]*?>0</');
});

it('says so rather than showing an empty table when no page views have been recorded', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    $response->assertSee('No page views recorded yet.');
});
