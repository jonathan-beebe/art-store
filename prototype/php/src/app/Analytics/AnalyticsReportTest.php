<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use DateTimeImmutable;

function recordListingEvent(Analytics $analytics, AnalyticsEventName $name, string $listingId, DateTimeImmutable $at): void
{
    $analytics->recordEvent(AnalyticsEvent::forListing($name, $listingId, 'cus_XYZ', $at));
}

it('tallies one listing\'s views, favorites, and cart adds, leaving another listing out', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at->modify('+1 hour'));
    recordListingEvent($analytics, AnalyticsEventName::ListingFavorite, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingCartAdd, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_OTHER', $at);
    $analytics->flush();

    $counts = AnalyticsReport::countsForListing('lst_ABC');

    expect($counts->views)->toBe(2)
        ->and($counts->favorites)->toBe(1)
        ->and($counts->cartAdds)->toBe(1);
});

it('tallies zero for a listing with no recorded events', function (): void {
    $counts = AnalyticsReport::countsForListing('lst_ABC');

    expect($counts->views)->toBe(0)
        ->and($counts->favorites)->toBe(0)
        ->and($counts->cartAdds)->toBe(0);
});

it('groups a listing\'s events by day and name from a cutoff onward', function (): void {
    $analytics = new Analytics;

    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', new DateTimeImmutable('2026-08-20T10:00:00+00:00'));
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', new DateTimeImmutable('2026-08-22T10:00:00+00:00'));
    recordListingEvent($analytics, AnalyticsEventName::ListingFavorite, 'lst_ABC', new DateTimeImmutable('2026-08-22T11:00:00+00:00'));
    $analytics->flush();

    $counts = AnalyticsReport::dailyCountsForListingSince('lst_ABC', new DateTimeImmutable('2026-08-21T00:00:00+00:00'));

    expect($counts)->toHaveCount(1)
        ->and($counts['2026-08-22'][AnalyticsEventName::ListingView->value])->toBe(1)
        ->and($counts['2026-08-22'][AnalyticsEventName::ListingFavorite->value])->toBe(1);
});

it('tallies every event name across the whole platform', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_OTHER', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingCartAdd, 'lst_ABC', $at);
    $analytics->flush();

    $counts = AnalyticsReport::platformCountsByName();

    expect($counts)->toHaveCount(2)
        ->and($counts[AnalyticsEventName::ListingView->value])->toBe(2)
        ->and($counts[AnalyticsEventName::ListingCartAdd->value])->toBe(1);
});
