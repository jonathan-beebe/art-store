<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\PageViewSite;

/**
 * @param  list<EventTotal>  $totals
 */
function eventTotalNamed(array $totals, string $name): EventTotal
{
    $found = collect($totals)->firstWhere('name', $name);
    assert($found instanceof EventTotal);

    return $found;
}

it('carries every event name plus a page.view roll-up, in that order', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $names = array_column(EventTotals::forRange($range), 'name');

    expect($names)->toBe([
        'listing.view', 'listing.favorite', 'listing.unfavorite', 'listing.cart_add',
        'checkout.open', 'order.place', 'order.pay', 'order.cancel', 'store.view', 'page.view',
    ]);
});

it('narrows by event name or label, case-insensitively', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    $names = array_column(EventTotals::forRange($range, 'FAVORITE'), 'name');

    expect($names)->toBe(['listing.favorite', 'listing.unfavorite']);
});

it('narrows to nothing when the search matches no event name or label', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(EventTotals::forRange($range, 'zzz-no-match'))->toBe([]);
});

it('sums the current and the previous window separately, per event name', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    // Current range: 2026-08-18 .. 2026-08-24. Two views inside it.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'view-1'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-22 09:00:00'), 'view-2'));

    // Previous range: 2026-08-11 .. 2026-08-17. One view inside it.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-15 09:00:00'), 'view-3'));

    // Outside both windows.
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-07-01 09:00:00'), 'view-4'));

    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $totals = EventTotals::forRange($range);
    $views = eventTotalNamed($totals, 'listing.view');

    expect($views->current)->toBe(2)
        ->and($views->previous)->toBe(1)
        ->and($views->change->text)->toBe('+100.0%');
});

it('lays the daily series out oldest first, over the current range only', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-18 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, $customer->id, $this->moment('2026-08-24 09:00:00')));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $totals = EventTotals::forRange($range);
    $favorites = eventTotalNamed($totals, 'listing.favorite');

    expect($favorites->daily)->toBe([1, 0, 0, 0, 0, 0, 1]);
});

it('counts distinct subjects and actors in the current range only', function (): void {
    $seller = $this->seller();
    $listingOne = $this->listing($seller);
    $listingTwo = $this->listing($seller);
    $customerOne = $this->verifiedCustomer();
    $customerTwo = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingOne->id, $customerOne->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingOne->id, $customerOne->id, $this->moment('2026-08-20 09:00:00'), 'b'));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listingTwo->id, $customerTwo->id, $this->moment('2026-08-21 09:00:00'), 'c'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $totals = EventTotals::forRange($range);
    $views = eventTotalNamed($totals, 'listing.view');

    expect($views->subjects)->toBe(2)
        ->and($views->actors)->toBe(2);
});

it('rolls page views up from page_view_counts with no subjects or actors', function (): void {
    $analytics = app(Analytics::class);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-19 09:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-19 15:00:00'));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $this->moment('2026-08-15 09:00:00'));
    $analytics->flush();

    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $totals = EventTotals::forRange($range);
    $pageViews = eventTotalNamed($totals, 'page.view');

    expect($pageViews->label)->toBe('Page views')
        ->and($pageViews->current)->toBe(2)
        ->and($pageViews->previous)->toBe(1)
        ->and($pageViews->subjects)->toBeNull()
        ->and($pageViews->actors)->toBeNull();
});

it('reads a name with nothing recorded as all zeroes rather than an absent row', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $totals = EventTotals::forRange($range);
    $cartAdds = eventTotalNamed($totals, 'listing.cart_add');

    expect($cartAdds->current)->toBe(0)
        ->and($cartAdds->previous)->toBe(0)
        ->and($cartAdds->change->text)->toBe('')
        ->and($cartAdds->daily)->toBe([0, 0, 0, 0, 0, 0, 0]);
});
