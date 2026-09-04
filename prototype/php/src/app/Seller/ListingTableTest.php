<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Listings\ListingStatus;
use App\Domain\Seller\ListingTableRow;
use DateTimeImmutable;
use RuntimeException;

/**
 * @param  list<ListingTableRow>  $rows
 */
function rowFor(array $rows, string $listingId): ListingTableRow
{
    foreach ($rows as $row) {
        if ($row->id === $listingId) {
            return $row;
        }
    }

    throw new RuntimeException("no row for {$listingId}");
}

it('reads a seller\'s own listings, leaving another seller\'s out', function (): void {
    $seller = $this->seller('Weasleys Wizard Wheezes');
    $mine = $this->listing($seller, ['title' => 'Extendable Ears']);
    $this->listing($this->seller('Ollivanders'), ['title' => 'Phoenix Feather Wand']);

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect(array_map(fn (ListingTableRow $row): string => $row->id, $rows))->toBe([$mine->id]);
});

it('reads price, dimensions, and the stock label off the listing', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Nimbus Two Thousand', 'price_cents' => 150000, 'dimensions' => '6 ft', 'quantity' => 3]);

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect($rows[0]->priceCents)->toBe(150000)
        ->and($rows[0]->dimensions)->toBe('6 ft')
        ->and($rows[0]->stockLabel())->toBe('3 in stock');
});

it('reads made to order stock as a null quantity', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['quantity' => null]);

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect($rows[0]->quantity)->toBeNull()
        ->and($rows[0]->stockLabel())->toBe('Made to order');
});

it('reads the seller badge label and tint off status and removal', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['status' => ListingStatus::Draft]);

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect($rows[0]->statusLabel)->toBe('Draft')
        ->and($rows[0]->statusTint)->toBe('gray');
});

it('reads the medium attribute label off the listing, batched across every listing', function (): void {
    $seller = $this->seller();
    $wand = $this->listing($seller, ['title' => 'Holly Wand']);
    $this->mediumAttribute($wand, 'Wood');
    $cloak = $this->listing($seller, ['title' => 'Invisibility Cloak']);

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect(rowFor($rows, $wand->id)->medium)->toBe('Wood')
        ->and(rowFor($rows, $cloak->id)->medium)->toBeNull();
});

it('reads a listing\'s ranged view, favorite, and cart-add counts', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Marauders Map']);
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, null, new DateTimeImmutable('2026-09-01 10:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, null, new DateTimeImmutable('2026-09-01 10:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, null, new DateTimeImmutable('2020-01-01 10:00:00')));
    $analytics->flush();

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect($rows[0]->views)->toBe(1)
        ->and($rows[0]->favorites)->toBe(1)
        ->and($rows[0]->cartAdds)->toBe(0);
});

it('sums sold and revenue from a paid order with a live fulfillment', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 500);
    $listingId = $fulfillment->order->items()->sole()->listing_id;

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));
    $row = rowFor($rows, $listingId);

    expect($row->sold)->toBe(1)
        ->and($row->revenueCents)->toBe(500);
});

it('leaves an order that was never paid off sold and revenue', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($seller, ['price_cents' => 500]);
    $this->orderFor($customer, $listing);

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));

    expect($rows[0]->sold)->toBe(0)
        ->and($rows[0]->revenueCents)->toBe(0);
});

it('leaves a declined fulfillment off sold and revenue', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 500);
    $listingId = $fulfillment->order->items()->sole()->listing_id;

    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $rows = ListingTable::forSeller($seller, AnalyticsRange::of(30, new DateTimeImmutable('2026-09-03')));
    $row = rowFor($rows, $listingId);

    expect($row->sold)->toBe(0)
        ->and($row->revenueCents)->toBe(0);
});
