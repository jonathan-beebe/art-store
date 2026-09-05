<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\ActorKind;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\PageViewSite;
use App\Models\Customer;

it('ranks actors by their busiest UTC hour, not by their total events', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $busy = $this->verifiedCustomer();
    $steady = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    // Busy: 3 events inside one hour — a peak of 3.
    foreach (['09:00:05', '09:00:10', '09:00:15'] as $time) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $busy->id, $this->moment("2026-08-19 {$time}")));
    }

    // Steady: 4 events spread across 4 hours — more events, a lower peak of 1.
    foreach (['08:00:00', '09:00:00', '10:00:00', '11:00:00'] as $time) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $steady->id, $this->moment("2026-08-19 {$time}")));
    }

    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $rows = ActorLeaderboard::forRange($range, ActorKindFilter::All, null);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->id)->toBe($busy->id)
        ->and($rows[0]->peakPerHour)->toBe(3)
        ->and($rows[0]->events)->toBe(3)
        ->and($rows[1]->id)->toBe($steady->id)
        ->and($rows[1]->peakPerHour)->toBe(1)
        ->and($rows[1]->events)->toBe(4);
});

it('fills in kind, who, and flags a peak past the velocity threshold', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00')));

    for ($i = 0; $i < 100; $i++) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-19 10:00:00')->modify("+{$i} seconds")));
    }

    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $rows = ActorLeaderboard::forRange($range, ActorKindFilter::All, null);
    $byId = collect($rows)->keyBy('id');

    $verifiedRow = $byId->get($verified->id);
    $anonymousRow = $byId->get($anonymous->id);
    assert($verifiedRow instanceof ActorSummary);
    assert($anonymousRow instanceof ActorSummary);

    expect($verifiedRow->kind)->toBe(ActorKind::Verified)
        ->and($verifiedRow->who)->toBe($verified->email)
        ->and($verifiedRow->flagged)->toBeFalse()
        ->and($anonymousRow->kind)->toBe(ActorKind::Anonymous)
        ->and($anonymousRow->who)->toBe('never signed in')
        ->and($anonymousRow->peakPerHour)->toBe(100)
        ->and($anonymousRow->flagged)->toBeTrue();
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

    expect(ActorLeaderboard::forRange($range, ActorKindFilter::Verified, null))->toHaveCount(1)
        ->and(ActorLeaderboard::forRange($range, ActorKindFilter::Anonymous, null))->toHaveCount(1)
        ->and(ActorLeaderboard::forRange($range, ActorKindFilter::All, null))->toHaveCount(2);
});

it('searches by actor id prefix and by email substring', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $hermione = Customer::factory()->create(['email' => 'hermione@example.com']);
    $ron = Customer::factory()->create(['email' => 'ron@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $hermione->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $ron->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $byPrefix = ActorLeaderboard::forRange($range, ActorKindFilter::All, $hermione->id);
    $byEmail = ActorLeaderboard::forRange($range, ActorKindFilter::All, 'ron@');

    expect($byPrefix)->toHaveCount(1)->and($byPrefix[0]->id)->toBe($hermione->id)
        ->and($byEmail)->toHaveCount(1)->and($byEmail[0]->id)->toBe($ron->id);
});

it('limits the leaderboard, keeping the busiest first', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $analytics = app(Analytics::class);

    for ($actor = 0; $actor < 3; $actor++) {
        $customer = $this->verifiedCustomer();
        for ($event = 0; $event <= $actor; $event++) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment(sprintf('2026-08-19 09:%02d:00', $event))));
        }
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $rows = ActorLeaderboard::forRange($range, ActorKindFilter::All, null, limit: 2);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->peakPerHour)->toBeGreaterThanOrEqual($rows[1]->peakPerHour);
});

it('ignores an event with no actor', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/', $this->moment('2026-08-19 09:00:00'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(ActorLeaderboard::forRange($range, ActorKindFilter::All, null))->toBe([]);
});
