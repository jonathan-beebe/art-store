<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Analytics\RecordPageView;
use App\Actions\Favorites\ToggleFavorite;
use App\Actions\Listings\RecordListingEvent;
use App\Domain\Analytics\PageViewSite;
use App\Domain\Listings\ListingEventType;

it('renders no list pane — a full-content section, not list+detail', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    $response->assertDontSee('xl:w-[400px]', escape: false);
});

it('shows page views by day, inside the seven-day window', function (): void {
    $record = app(RecordPageView::class);
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 09:00:00'));
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 15:00:00'));
    $record(PageViewSite::Seller, '/seller', $this->moment('2026-08-20 09:00:00'));

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    expect($response->getContent())->toMatch('/data-day="2026-08-20"[\s\S]*?data-cell="count"[^>]*>3</');
});

it('leaves a day outside the seven-day window out', function (): void {
    app(RecordPageView::class)(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-01 09:00:00'));

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    $response->assertDontSee('2026-08-01');
});

it('shows page views by route pattern, busiest first', function (): void {
    $record = app(RecordPageView::class);
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 09:00:00'));
    $record(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 15:00:00'));
    $record(PageViewSite::Seller, '/seller', $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-pattern="shop \/art\/\{listing\}"[\s\S]*?data-cell="count"[^>]*>2</')
        ->toMatch('/data-pattern="seller \/seller"[\s\S]*?data-cell="count"[^>]*>1</');
});

it('counts listing events by type', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    app(RecordListingEvent::class)($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    app(ToggleFavorite::class)($customer, $listing, $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-stat="event-view"[\s\S]*?>1</')
        ->toMatch('/data-stat="event-favorite"[\s\S]*?>1</')
        ->toMatch('/data-stat="event-cart_add"[\s\S]*?>0</');
});

it('says so rather than showing an empty table when no page views have been recorded', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertOk();
    $response->assertSee('No page views recorded yet.');
});

it('sends a guest to the admin login page', function (): void {
    $this->get('/admin/stats')->assertRedirect(route('auth.admin.login'));
});
