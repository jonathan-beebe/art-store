<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Http\Middleware\LogRequestStory;
use Illuminate\Http\Request;

it('renders 200 with every actor in the range', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors');

    $response->assertOk();
    $response->assertSee('All actors');
    $response->assertSee($customer->id);
});

it('sorts by most active', function (): void {
    $listing = $this->listing($this->seller());
    $busy = $this->verifiedCustomer();
    $quiet = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $busy->id, $this->moment("2026-08-19 09:0{$i}:00")));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $quiet->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=active');

    $response->assertOk();
    $response->assertSeeInOrder([$busy->id, $quiet->id]);
});

it('sorts by most recent', function (): void {
    $listing = $this->listing($this->seller());
    $recent = $this->verifiedCustomer();
    $stale = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $stale->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $recent->id, $this->moment('2026-08-23 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=recent');

    $response->assertOk();
    $response->assertSeeInOrder([$recent->id, $stale->id]);
});

it('pages past the first page of actors', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);
    $ids = [];

    // One more actor than a page holds, each with a distinct last-seen
    // minute so "most recent" order is unambiguous — one event apiece
    // keeps the fixture cheap.
    $total = ActorController::PER_PAGE + 1;
    for ($minutesAgo = $total; $minutesAgo >= 1; $minutesAgo--) {
        $customer = $this->verifiedCustomer();
        $ids[] = $customer->id;
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')->modify('+'.($total - $minutesAgo).' minutes')));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $firstPage = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=recent');
    $secondPage = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=recent&page=2');

    $firstPage->assertOk()->assertSee($ids[$total - 1])->assertDontSee($ids[0]);
    $secondPage->assertOk()->assertSee($ids[0]);
});

it('clamps a page past the end to the last page', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?page=999');

    $response->assertOk();
    $response->assertSee($customer->id);
});

it('narrows by actor kind', function (): void {
    $listing = $this->listing($this->seller());
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?actors=verified');

    $response->assertOk();
    $response->assertSee($verified->id)->assertDontSee($anonymous->id);
});

it('searches by actor id', function (): void {
    $listing = $this->listing($this->seller());
    $hermione = $this->verifiedCustomer();
    $ron = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $ron->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?q='.$hermione->id);

    $response->assertOk();
    $response->assertSee($hermione->id)->assertDontSee($ron->id);
});

it('searches by email', function (): void {
    $listing = $this->listing($this->seller());
    $hermione = $this->verifiedCustomer();
    $ron = $this->verifiedCustomer();
    $hermione->update(['email' => 'hermione@example.com']);
    $ron->update(['email' => 'ron@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $ron->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?q=hermione');

    $response->assertOk();
    $response->assertSee('hermione@example.com')->assertDontSee('ron@example.com');
});

it('searches by ip', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $request = Request::create('/', server: ['REMOTE_ADDR' => '185.220.101.42']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?q=185.220.101.42');

    $response->assertOk();
    $response->assertSee($customer->id);
});

it('answers 400 for an unrecognised sort', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors?sort=popular');

    $response->assertStatus(400);
});
