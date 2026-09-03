<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Actions\Favorites\ToggleFavorite;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\FinalizeOrder;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\PageViewSite;
use App\Http\Middleware\LogRequestStory;
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

it('shows the funnel above the events table, with its rates and the cancelled note', function () use ($bindSession): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));

    $paid = $this->orderFor($customer, $listing);
    $cancelled = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    $bindSession('sess-pay');
    app(FinalizeOrder::class)($paid, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    $bindSession('sess-cancel');
    app(CancelOrder::class)($cancelled, $this->moment('2026-08-20 11:00:00'));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics?range=7');

    $response->assertOk();
    $response->assertSeeInOrder(['Funnel', 'Visitors', 'Orders paid', 'Events']);
    $response->assertSee('1 cancelled');
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
        ->expectsDatabaseQueryCount(12, 'analytics')
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
    // The funnel above the table always shows every step's label, so the
    // search is asserted by the machine name only the table row carries.
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
