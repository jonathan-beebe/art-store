<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\RecordListingEvent;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingEventType;
use App\Domain\Reports\DailyActivity;
use App\Models\Listing;
use App\Models\Seller;
use DateTimeImmutable;

$recordedActivity = function (Seller $seller): Listing {
    $listing = test()->listing($seller);
    $recordListingEvent = app(RecordListingEvent::class);
    $recordListingEvent($listing, null, ListingEventType::View, test()->moment('2026-08-20 09:00:00'));
    $recordListingEvent($listing, null, ListingEventType::View, test()->moment('2026-08-20 10:00:00'));
    $recordListingEvent($listing, null, ListingEventType::Favorite, test()->moment('2026-08-20 11:00:00'));
    $recordListingEvent($listing, null, ListingEventType::CartAdd, test()->moment('2026-08-20 12:00:00'));

    return $listing;
};

it('renders the activity page', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
});

it('hides another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertNotFound();
});

it('totals the events of the listing', function () use ($recordedActivity): void {
    $seller = $this->seller();
    $listing = $recordedActivity($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('listing', function (Listing $listing): bool {
        return $listing->views_count === 2 && $listing->favorites_count === 1 && $listing->cart_adds_count === 1;
    });
});

it('breaks the last fourteen days down by day', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('days', fn (array $days): bool => count($days) === 14);
    $response->assertViewHas('windowDays', 14);
});

it('counts todays events on todays row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    app(RecordListingEvent::class)($listing, null, ListingEventType::View, new DateTimeImmutable(now()->toDateTimeString()));

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('days', function (array $days): bool {
        $today = $days[13];

        return $today instanceof DailyActivity && $today->views === 1;
    });
});

it('leaves events older than the window off the breakdown', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2020-01-01 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('days', fn (array $days): bool => array_sum(
        array_map(fn (DailyActivity $day): int => $day->total(), $days),
    ) === 0);
});

it('lists the sales of the listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'quantity' => 3]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('sales', fn ($sales): bool => $sales->count() === 1);
    $response->assertSee("#{$order->id}");
});

it('renders on a fixed number of queries however many events the listing recorded', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $recordListingEvent = app(RecordListingEvent::class);
    foreach (range(1, 20) as $hour) {
        $recordListingEvent($listing, null, ListingEventType::View, new DateTimeImmutable(now()->toDateTimeString()));
    }

    $response = $this->actingAs($seller, 'seller')
        ->expectsDatabaseQueryCount(4)
        ->get("/seller/listings/{$listing->id}");

    $response->assertOk();
});
