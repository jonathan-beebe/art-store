<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use App\Http\Middleware\LogRequestStory;
use App\Logging\RequestMarks;
use Illuminate\Http\Request;

/**
 * Binds an in-flight request carrying the given session cookie, so the
 * next recorded event reads it back from
 * {@see \App\Analytics\RequestFacts::current()}.
 */
$bindChannelControllerSession = function (string $sessionId): void {
    $request = Request::create('/', cookies: [RequestMarks::SESSION_COOKIE => $sessionId]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
};

it('renders 200 with visitors, views, cart adds, and orders per channel, ordered by visitors', function () use ($bindChannelControllerSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-direct-a', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-direct-b', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $bindChannelControllerSession('sess-direct-a');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));

    $analytics->recordVisit(new AnalyticsVisit('sess-campaign', $this->moment('2026-08-20 09:00:00'), '/', null, 'newsletter', 'email', 'sept', null, null, null));
    $bindChannelControllerSession('sess-campaign');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-20 09:05:00')));

    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels');

    $response->assertOk();
    $response->assertSeeInOrder(['Direct', 'Email campaign: sept']);
});

it('shows the change against the range before', function (): void {
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-old', $this->moment('2026-08-13 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-new-a', $this->moment('2026-08-20 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-new-b', $this->moment('2026-08-21 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels?range=7');

    $response->assertOk();
    $response->assertSee('+100.0%');
});

it('carries the range through the channel link', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordVisit(new AnalyticsVisit('sess-direct', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels?range=7');

    $response->assertOk();
    $response->assertSee('href="'.route('admin.analytics.channels.show', ['key' => 'direct', 'range' => '7']).'"', escape: false);
});

it('lists a channel\'s own visits, the actor linked when the visit has one', function (): void {
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-anon', $this->moment('2026-08-19 09:00:00'), '/art/starry-night', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-known', $this->moment('2026-08-20 09:00:00'), '/', null, null, null, null, null, null, $customer->id));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels/direct');

    $response->assertOk();
    $response->assertSee('/art/starry-night');
    $response->assertSee($customer->id);
    $response->assertSee('sess-anon');
});

it('answers 404 for a channel key nothing in the range derives to', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels/search:google');

    $response->assertNotFound();
});

it('renders the channels page on a fixed number of queries however many channels the range holds', function () use ($bindChannelControllerSession): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (['sess-a', 'sess-b', 'sess-c'] as $i => $sessionId) {
        $analytics->recordVisit(new AnalyticsVisit($sessionId, $this->moment('2026-08-19 09:00:00'), '/', "ref{$i}.example.com", null, null, null, null, null, null));
        $bindChannelControllerSession($sessionId);
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(1, 'sqlite')
        ->expectsDatabaseQueryCount(3, 'analytics')
        ->get('/admin/analytics/channels');

    $response->assertOk();
});

it('renders one channel\'s visits page on a fixed number of queries however many visits the range holds', function (): void {
    $analytics = app(Analytics::class);

    foreach (range(1, 15) as $i) {
        $analytics->recordVisit(new AnalyticsVisit("sess-{$i}", $this->moment('2026-08-19 09:00:00')->modify("+{$i} minutes"), '/', null, null, null, null, null, null, null));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(1, 'sqlite')
        ->expectsDatabaseQueryCount(2, 'analytics')
        ->get('/admin/analytics/channels/direct');

    $response->assertOk();
});
