<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Http\Middleware\LogRequestStory;
use Illuminate\Http\Request;

it('renders 200 with the range compared against the range before it', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee('Analytics');
    $response->assertSee(AnalyticsEventName::ListingView->value);
    $response->assertSee('Page views');
});

it('shows the right counts, changes, and labels for a seeded event', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    // Current 7-day range (2026-08-18 .. 2026-08-24): two views.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:00:00'), 'b'));
    // Previous 7-day range (2026-08-11 .. 2026-08-17): one view.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-15 09:00:00'), 'c'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=7');

    $response->assertOk();
    $response->assertSee('Listing views')
        ->assertSeeInOrder(['listing.view', '2', '1', '+100.0%']);
});

it('carries q through the range links', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q=hermione');

    $response->assertOk();
    expect($response->getContent())->toMatch('/admin\/analytics\?[^"]*range=30[^"]*q=hermione|admin\/analytics\?[^"]*q=hermione[^"]*range=30/');
});

it('answers 400 for an unrecognised range', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=14');

    $response->assertStatus(400);
});

it('narrows the actor leaderboard by search', function (): void {
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
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q=hermione');

    $response->assertOk();
    $response->assertSee('hermione@example.com');
    $response->assertDontSee('ron@example.com');
});

it('renders the entry page on a fixed number of queries however many actors the range holds', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);

    foreach (range(1, 12) as $i) {
        $customer = $this->verifiedCustomer();
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    }
    $analytics->flush();

    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(2, 'sqlite')
        ->expectsDatabaseQueryCount(8, 'analytics')
        ->get('/admin/analytics');

    $response->assertOk();
});

it('narrows the events table by event name', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q=favorite');

    $response->assertOk();
    $response->assertSee('Favorites');
    $response->assertDontSee('Listing views');
    $response->assertDontSee('Cart adds');
});

it('shows the jump row for a listing id prefix', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'The Burrow at Dusk']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q='.substr($listing->id, 0, 10));

    $response->assertOk();
    $response->assertSee('listing · The Burrow at Dusk');
});

it('shows the jump row for an ip that names one actor', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $request = Request::create('/', server: ['REMOTE_ADDR' => '185.220.101.42']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q=185.220.101.42');

    $response->assertOk();
    $response->assertSee('anonymous customer · never signed in');
});

it('shows no jump row when the search matches nothing uniquely', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q=nothing-matches-this');

    $response->assertOk();
    $response->assertDontSee('Open its events');
});
