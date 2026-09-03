<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Actions\Favorites\ToggleFavorite;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\PageViewSite;
use App\Http\Middleware\LogRequestStory;
use App\Models\Funnel;
use App\Support\RequestMarks;
use Illuminate\Http\Request;

/**
 * Binds an in-flight request carrying the given session cookie, so the next
 * recorded event reads it back from {@see \App\Analytics\RequestFacts::current()}.
 */
$bindSession = function (string $sessionId): void {
    $request = Request::create('/', cookies: [RequestMarks::SESSION_COOKIE => $sessionId]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
};

it('redirects /admin/stats to /admin/analytics permanently for a signed-in admin', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/stats');

    $response->assertStatus(301);
    $response->assertRedirect('/admin/analytics');
});

it('sends a guest hitting /admin/stats to admin sign-in, not the redirect', function (): void {
    $response = $this->get('/admin/stats');

    $response->assertRedirect(route('auth.admin.login'));
});

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

it('shows a tile per funnel above the events table, with its end-to-end conversion', function () use ($bindSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);
    Funnel::factory()->create(['name' => 'Storefront', 'steps' => ['listing.view', 'listing.cart_add']]);

    $bindSession('sess-1');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=7');

    $response->assertOk();
    $response->assertSeeInOrder(['Funnels', 'Storefront', '100%', 'Events']);
});

it('shows a message and a link to define one when there are no funnels', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee('No funnels yet.');
    $response->assertSee('href="'.route('admin.funnels.create').'"', escape: false);
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

it('renders the entry page on a fixed number of queries however many actors or funnels the range holds', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);

    foreach (range(1, 12) as $i) {
        $customer = $this->verifiedCustomer();
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    }
    $analytics->flush();

    // Two funnels: one funnel query (two analytics statements) per tile —
    // a fixed cost per funnel, never per step or per actor.
    Funnel::factory()->create(['name' => 'Storefront', 'position' => 1, 'steps' => ['listing.view', 'listing.cart_add']]);
    Funnel::factory()->create(['name' => 'Channel', 'position' => 2, 'steps' => ['checkout.open', 'order.pay']]);

    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(3, 'sqlite')
        ->expectsDatabaseQueryCount(14, 'analytics')
        ->get('/admin/analytics');

    $response->assertOk();
});

it('shows a channels section naming the top three by visitors, and an all-channels link', function (): void {
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-a', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-b', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-c', $this->moment('2026-08-19 09:00:00'), '/', null, 'newsletter', 'email', 'sept', null, null, null));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics');

    $response->assertOk();
    $response->assertSee('Channels');
    $response->assertSee('Direct (2)');
    $response->assertSee('Email campaign: sept (1)');
    $response->assertSee('href="'.route('admin.analytics.channels.index').'"', escape: false);
});

it('narrows the events table by event name', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?q=favorite');

    $response->assertOk();
    $response->assertSee('listing.favorite');
    $response->assertDontSee('listing.view');
    $response->assertDontSee('listing.cart_add');
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

it('shows every listing event name at least once, zero-filled for one nobody has triggered', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:00:00')));
    app(ToggleFavorite::class)($customer, $listing, $this->moment('2026-08-20 09:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=7');

    $response->assertOk()
        ->assertSeeInOrder([
            'listing.view', '1',
            'listing.favorite', '1',
            'listing.unfavorite', '0',
            'listing.cart_add', '0',
        ]);
});

it('shows page views by day in the daily bar tooltip', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-20 15:00:00'));
    $analytics->recordPageView(PageViewSite::Seller, '/seller', $this->moment('2026-08-20 09:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=7');

    $response->assertOk();
    expect($response->getContent())->toContain('Aug 20: 3');
});

it('draws ninety daily bars per event row at range=90, one rect per day', function (): void {
    $this->travelTo($this->moment('2026-09-02 12:00:00'));

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=90');

    $response->assertOk();

    $rowCount = count(AnalyticsEventName::cases()) + 1; // named events plus the page.view roll-up
    expect(substr_count((string) $response->getContent(), '<rect'))->toBe(90 * $rowCount);
});
