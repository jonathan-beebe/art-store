<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\PageViewSite;
use App\Models\Customer;

it('reads first seen as the actor\'s earliest event ever, not bounded by the range', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    // Well before the 7-day range below (2026-08-18 .. 2026-08-24).
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-01-01 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $rows = ActorAggregates::forRange($range, ActorKindFilter::All, null);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->firstSeenAt->format('Y-m-d'))->toBe('2026-01-01')
        ->and($rows[0]->lastSeenAt->format('Y-m-d'))->toBe('2026-08-19');
});

it('narrows to one actor kind', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(ActorAggregates::forRange($range, ActorKindFilter::Verified, null))->toHaveCount(1)
        ->and(ActorAggregates::forRange($range, ActorKindFilter::Anonymous, null))->toHaveCount(1)
        ->and(ActorAggregates::forRange($range, ActorKindFilter::All, null))->toHaveCount(2);
});

it('searches by actor id prefix, email substring, and ip', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $hermione = Customer::factory()->create(['email' => 'hermione@example.com']);
    $ron = Customer::factory()->create(['email' => 'ron@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $ron->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $byPrefix = ActorAggregates::forRange($range, ActorKindFilter::All, $hermione->id);
    $byEmail = ActorAggregates::forRange($range, ActorKindFilter::All, 'ron@');

    expect($byPrefix)->toHaveCount(1)->and($byPrefix[0]->id)->toBe($hermione->id)
        ->and($byEmail)->toHaveCount(1)->and($byEmail[0]->id)->toBe($ron->id);
});

it('ignores an event with no actor', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/', $this->moment('2026-08-19 09:00:00'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(ActorAggregates::forRange($range, ActorKindFilter::All, null))->toBe([]);
});
