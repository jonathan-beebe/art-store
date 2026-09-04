<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\ChangeDirection;
use App\Domain\Seller\ActivityTotal;
use App\Models\Fulfillment;
use App\Models\Seller;
use DateTimeImmutable;
use RuntimeException;

function activityNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-04 12:00:00');
}

function activityFor(Seller $seller, int $days = 30): ListingActivity
{
    return ListingActivity::for($seller, AnalyticsRange::of($days, activityNow()));
}

function recordListingEvent(AnalyticsEventName $name, string $listingId, string $when, int $times = 1): void
{
    $analytics = app(Analytics::class);

    for ($i = 0; $i < $times; $i++) {
        $analytics->recordEvent(AnalyticsEvent::forListing($name, $listingId, null, new DateTimeImmutable($when)));
    }

    $analytics->flush();
}

/**
 * @param  list<ActivityTotal>  $totals
 */
function activityTotal(array $totals, string $label): ActivityTotal
{
    foreach ($totals as $total) {
        if ($total->label === $label) {
            return $total;
        }
    }

    throw new RuntimeException("no total labelled {$label}");
}

function soldOn(Fulfillment $parcel, string $day): Fulfillment
{
    $parcel->order->forceFill(['placed_at' => new DateTimeImmutable($day)])->save();

    return $parcel->refresh();
}

it('names the four totals in the order the page reads them', function (): void {
    $activity = activityFor($this->seller('The Burrow Craftworks'));

    expect(array_map(fn (ActivityTotal $total): string => $total->label, $activity->totals))
        ->toBe(['Views', 'Favorites', 'Cart adds', 'Sold']);
});

it('sums the ranges views, favorites, and cart adds across every listing', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $owls = $this->listing($seller, ['title' => 'Nine Owls']);
    $bowl = $this->listing($seller, ['title' => 'Tea Bowl']);

    recordListingEvent(AnalyticsEventName::ListingView, $owls->id, '2026-08-25 10:00:00', times: 3);
    recordListingEvent(AnalyticsEventName::ListingView, $bowl->id, '2026-08-26 10:00:00', times: 2);
    recordListingEvent(AnalyticsEventName::ListingFavorite, $owls->id, '2026-08-25 11:00:00');
    recordListingEvent(AnalyticsEventName::ListingCartAdd, $bowl->id, '2026-08-26 11:00:00');

    $totals = activityFor($seller)->totals;

    expect(activityTotal($totals, 'Views')->count)->toBe(5)
        ->and(activityTotal($totals, 'Favorites')->count)->toBe(1)
        ->and(activityTotal($totals, 'Cart adds')->count)->toBe(1);
});

it('reads the range against the range before it', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $listing = $this->listing($seller, ['title' => 'Nine Owls']);

    // A thirty-day range ending Sep 4 opens Aug 6; the range before it
    // opens Jul 7.
    recordListingEvent(AnalyticsEventName::ListingView, $listing->id, '2026-08-25 10:00:00', times: 4);
    recordListingEvent(AnalyticsEventName::ListingView, $listing->id, '2026-07-20 10:00:00', times: 2);

    $views = activityTotal(activityFor($seller)->totals, 'Views');

    expect($views->count)->toBe(4)
        ->and($views->change->text)->toBe('+100.0%')
        ->and($views->change->direction)->toBe(ChangeDirection::Up);
});

it('counts the units sold inside the range', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    soldOn($this->paidFulfillmentFor($seller), '2026-08-25 10:00:00');
    soldOn($this->paidFulfillmentFor($seller), '2026-07-20 10:00:00');

    $sold = activityTotal(activityFor($seller)->totals, 'Sold');

    expect($sold->count)->toBe(1)
        ->and($sold->change->text)->toBe('0.0%');
});

it('lists the five listings drawing the most views, most looked at first', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $titles = ['Nine Owls', 'Tea Bowl', 'Ochre Runner', 'Copper Cauldron', 'Garden Gnome', 'Tea Leaf Study'];

    foreach ($titles as $index => $title) {
        $listing = $this->listing($seller, ['title' => $title]);
        recordListingEvent(AnalyticsEventName::ListingView, $listing->id, '2026-08-25 10:00:00', times: 10 - $index);
    }

    $rows = activityFor($seller)->rows;

    expect(array_map(fn (OverviewListingRow $row): string => $row->listing->title, $rows))
        ->toBe(['Nine Owls', 'Tea Bowl', 'Ochre Runner', 'Copper Cauldron', 'Garden Gnome']);
});

it('gives each row the listings own table row, its ranged units sold, and its page', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $parcel = soldOn($this->paidFulfillmentFor($seller, priceCents: 6000), '2026-08-25 10:00:00');
    $listingId = $parcel->order->items()->sole()->listing_id;
    recordListingEvent(AnalyticsEventName::ListingView, $listingId, '2026-08-25 10:00:00', times: 4);

    $row = activityFor($seller)->rows[0];

    expect($row->listing->id)->toBe($listingId)
        ->and($row->listing->views)->toBe(4)
        ->and($row->sold)->toBe(1)
        ->and($row->href)->toBe(route('seller.listings.show', ['listing' => $listingId, 'range' => 30]));
});

it('draws one bar per day of the strip, capped at thirty', function (int $days, int $stripDays): void {
    $seller = $this->seller('The Burrow Craftworks');
    $listing = $this->listing($seller, ['title' => 'Nine Owls']);
    recordListingEvent(AnalyticsEventName::ListingView, $listing->id, '2026-09-01 10:00:00', times: 2);

    $activity = activityFor($seller, $days);

    expect($activity->stripDays)->toBe($stripDays)
        ->and($activity->rows[0]->strip)->toHaveCount($stripDays);
})->with([
    'a week' => [7, 7],
    'a month' => [30, 30],
    'a quarter, capped' => [90, 30],
]);

it('gives a day nobody looked its own empty bar', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $listing = $this->listing($seller, ['title' => 'Nine Owls']);
    recordListingEvent(AnalyticsEventName::ListingView, $listing->id, '2026-09-04 10:00:00', times: 5);

    $strip = activityFor($seller, 7)->rows[0]->strip;

    expect($strip)->toHaveCount(7)
        ->and($strip[6]->tip)->toBe('Sep 4: 5')
        ->and($strip[0]->tip)->toBe('Aug 29: 0');
});

it('hands back no rows for a seller with no listings', function (): void {
    $activity = activityFor($this->seller('The Burrow Craftworks'));

    expect($activity->rows)->toBe([])
        ->and(activityTotal($activity->totals, 'Views')->count)->toBe(0);
});

it('leaves another sellers listings out', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $theirs = $this->listing($this->seller('Ollivanders'), ['title' => 'Phoenix Feather Wand']);
    recordListingEvent(AnalyticsEventName::ListingView, $theirs->id, '2026-08-25 10:00:00', times: 9);

    $activity = activityFor($seller);

    expect($activity->rows)->toBe([])
        ->and(activityTotal($activity->totals, 'Views')->count)->toBe(0);
});

it('carries the range onto each rows link, so the listing opens on the same window', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $listing = $this->listing($seller, ['title' => 'Nine Owls']);
    recordListingEvent(AnalyticsEventName::ListingView, $listing->id, '2026-09-01 10:00:00');

    expect(activityFor($seller, 7)->rows[0]->href)
        ->toBe(route('seller.listings.show', ['listing' => $listing->id, 'range' => 7]));
});
