<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Http\Middleware\LogRequestStory;
use App\Support\RequestMarks;
use Illuminate\Http\Request;

/**
 * Binds an in-flight request carrying the given session cookie, so the
 * next recorded event reads it back from
 * {@see \App\Analytics\RequestFacts::current()} — named apart from
 * `FunnelTest.php`'s own copy since a parallel test run splits files
 * across worker processes and never guarantees both are loaded together.
 */
function bindChannelTableSession(string $sessionId): void
{
    $request = Request::create('/', cookies: [RequestMarks::SESSION_COOKIE => $sessionId]);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    app()->instance('request', $request);
}

it('reads visitors, views, cart adds, orders placed, and orders paid per channel for the range', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit(
        'sess-campaign', $this->moment('2026-08-19 09:00:00'), '/art/starry-night',
        null, 'newsletter', 'email', 'sept', null, null, null,
    ));
    bindChannelTableSession('sess-campaign');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:05:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $customer->id, $this->moment('2026-08-19 09:06:00')));
    $analytics->recordEvent(AnalyticsEvent::forOrder(AnalyticsEventName::OrderPlace, 'ord_00000000000000000000000001', $customer->id, $this->moment('2026-08-19 09:07:00')));
    $analytics->recordEvent(AnalyticsEvent::forOrder(AnalyticsEventName::OrderPay, 'ord_00000000000000000000000001', $customer->id, $this->moment('2026-08-19 09:08:00')));

    $analytics->recordVisit(new AnalyticsVisit(
        'sess-direct', $this->moment('2026-08-20 09:00:00'), '/',
        null, null, null, null, null, null, null,
    ));
    bindChannelTableSession('sess-direct');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:05:00')));

    $analytics->flush();

    $rows = ChannelTable::forRange($range);

    $campaign = collect($rows)->firstWhere('channelKey', 'campaign:sept');
    $direct = collect($rows)->firstWhere('channelKey', 'direct');
    assert($campaign instanceof ChannelRow);
    assert($direct instanceof ChannelRow);

    expect($campaign->label)->toBe('Email campaign: sept')
        ->and($campaign->visitors->current)->toBe(1)
        ->and($campaign->views->current)->toBe(1)
        ->and($campaign->cartAdds->current)->toBe(1)
        ->and($campaign->ordersPlaced->current)->toBe(1)
        ->and($campaign->ordersPaid->current)->toBe(1);

    expect($direct->visitors->current)->toBe(1)
        ->and($direct->views->current)->toBe(1)
        ->and($direct->cartAdds->current)->toBe(0)
        ->and($direct->ordersPlaced->current)->toBe(0)
        ->and($direct->ordersPaid->current)->toBe(0);
});

it('carries the range before\'s numbers separately from the range\'s own', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-old', $this->moment('2026-08-13 09:00:00'), '/', null, null, null, null, null, null, null));
    bindChannelTableSession('sess-old');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-13 09:05:00')));

    $analytics->recordVisit(new AnalyticsVisit('sess-new', $this->moment('2026-08-20 09:00:00'), '/', null, null, null, null, null, null, null));
    bindChannelTableSession('sess-new');
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-20 09:05:00')));

    $analytics->flush();

    $direct = collect(ChannelTable::forRange($range))->firstWhere('channelKey', 'direct');
    assert($direct instanceof ChannelRow);

    expect($direct->visitors->current)->toBe(1)
        ->and($direct->visitors->previous)->toBe(1)
        ->and($direct->views->current)->toBe(1)
        ->and($direct->views->previous)->toBe(1);
});

it('folds two raw attribution tuples that derive the same channel key into one row', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-x', $this->moment('2026-08-19 09:00:00'), '/', 'x.com', null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-twitter', $this->moment('2026-08-20 09:00:00'), '/', 'twitter.com', null, null, null, null, null, null));
    $analytics->flush();

    $rows = ChannelTable::forRange($range);
    $matching = collect($rows)->where('channelKey', 'social:x/twitter');
    $row = $matching->first();
    assert($row instanceof ChannelRow);

    expect($matching)->toHaveCount(1)
        ->and($row->visitors->current)->toBe(2);
});

it('orders rows by visitors, most first', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-direct-a', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-direct-b', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-campaign', $this->moment('2026-08-19 09:00:00'), '/', null, 'newsletter', 'email', 'sept', null, null, null));
    $analytics->flush();

    $rows = ChannelTable::forRange($range);

    expect(array_column($rows, 'channelKey'))->toBe(['direct', 'campaign:sept']);
});

it('returns an empty list when nothing was recorded', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(ChannelTable::forRange($range))->toBe([]);
});
