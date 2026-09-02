<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;

it('sorts by most active, ties broken by actor id', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $busy = $this->verifiedCustomer();
    $tiedA = $this->verifiedCustomer();
    $tiedB = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $busy->id, $this->moment("2026-08-19 09:0{$i}:00")));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $tiedA->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $tiedB->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $result = ActorList::forRange($range, ActorSort::Active, ActorKindFilter::All, null, page: 1);

    $tiedIds = collect([$tiedA->id, $tiedB->id])->sort()->values()->all();

    expect($result->rows)->toHaveCount(3)
        ->and($result->rows[0]->id)->toBe($busy->id)
        ->and($result->rows[1]->id)->toBe($tiedIds[0])
        ->and($result->rows[2]->id)->toBe($tiedIds[1]);
});

it('sorts by most recent, ties broken by actor id', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $recent = $this->verifiedCustomer();
    $tiedA = $this->verifiedCustomer();
    $tiedB = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $recent->id, $this->moment('2026-08-23 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $tiedA->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $tiedB->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $result = ActorList::forRange($range, ActorSort::Recent, ActorKindFilter::All, null, page: 1);

    $tiedIds = collect([$tiedA->id, $tiedB->id])->sort()->values()->all();

    expect($result->rows)->toHaveCount(3)
        ->and($result->rows[0]->id)->toBe($recent->id)
        ->and($result->rows[1]->id)->toBe($tiedIds[0])
        ->and($result->rows[2]->id)->toBe($tiedIds[1]);
});

it('pages the sorted list', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $analytics = app(Analytics::class);
    $ids = [];

    // Five actors with a strictly descending events count, so "most
    // active" order is unambiguous across pages.
    for ($actor = 5; $actor >= 1; $actor--) {
        $customer = $this->verifiedCustomer();
        $ids[] = $customer->id;
        for ($event = 0; $event < $actor; $event++) {
            $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment(sprintf('2026-08-19 09:%02d:00', $event))));
        }
    }
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $firstPage = ActorList::forRange($range, ActorSort::Active, ActorKindFilter::All, null, page: 1, perPage: 2);
    $secondPage = ActorList::forRange($range, ActorSort::Active, ActorKindFilter::All, null, page: 2, perPage: 2);

    expect($firstPage->page->totalCount)->toBe(5)
        ->and($firstPage->page->count)->toBe(3)
        ->and($firstPage->rows)->toHaveCount(2)
        ->and($firstPage->rows[0]->id)->toBe($ids[0])
        ->and($firstPage->rows[1]->id)->toBe($ids[1])
        ->and($secondPage->rows)->toHaveCount(2)
        ->and($secondPage->rows[0]->id)->toBe($ids[2])
        ->and($secondPage->rows[1]->id)->toBe($ids[3]);
});

it('clamps a page past the end to the last page', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $result = ActorList::forRange($range, ActorSort::Active, ActorKindFilter::All, null, page: 99, perPage: 25);

    expect($result->page->number)->toBe(1)
        ->and($result->rows)->toHaveCount(1);
});

it('narrows by kind and by search', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $verified = $this->verifiedCustomer();
    $anonymous = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $verified->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $anonymous->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $byKind = ActorList::forRange($range, ActorSort::Active, ActorKindFilter::Verified, null, page: 1);
    $bySearch = ActorList::forRange($range, ActorSort::Active, ActorKindFilter::All, $verified->id, page: 1);

    expect($byKind->rows)->toHaveCount(1)->and($byKind->rows[0]->id)->toBe($verified->id)
        ->and($bySearch->rows)->toHaveCount(1)->and($bySearch->rows[0]->id)->toBe($verified->id);
});
