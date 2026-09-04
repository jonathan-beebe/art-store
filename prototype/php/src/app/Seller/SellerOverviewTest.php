<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\ChangeDirection;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Seller\PayoutEstimate;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Seller;
use DateTimeImmutable;
use RuntimeException;

function overviewNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-04 12:00:00');
}

/**
 * @return list<OverviewTile>
 */
function overviewTiles(Seller $seller, int $days = 30): array
{
    $now = overviewNow();

    return SellerOverview::for(
        $seller,
        AnalyticsRange::of($days, $now),
        NextPayout::for($seller, $now)->estimate,
    )->tiles();
}

/**
 * The test helpers place every order at one fixed moment; this moves one
 * onto the day a test is asking about, which is the fact every figure on
 * the page is bucketed by.
 */
function placedOn(Fulfillment $parcel, string $day): Fulfillment
{
    $parcel->order->forceFill(['placed_at' => new DateTimeImmutable($day)])->save();

    return $parcel->refresh();
}

/**
 * @param  list<OverviewTile>  $tiles
 */
function overviewTile(array $tiles, string $label): OverviewTile
{
    foreach ($tiles as $tile) {
        if ($tile->label === $label) {
            return $tile;
        }
    }

    throw new RuntimeException("no tile labelled {$label}");
}

it('hands back the three tiles a seller reads their business by', function (): void {
    $tiles = overviewTiles($this->seller('The Burrow Craftworks'));

    expect(array_map(fn (OverviewTile $tile): string => $tile->label, $tiles))
        ->toBe(['Customers', 'Orders', 'Earnings']);
});

it('counts every buyer, however far back their first order was', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->deliveredFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter'])), '2025-01-05 10:00:00');
    placedOn($this->deliveredFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley'])), '2026-08-25 10:00:00');

    $tile = overviewTile(overviewTiles($seller), 'Customers');

    expect($tile->value)->toBe('2')
        ->and($tile->changeText)->toBe('+1 new')
        ->and($tile->changeDirection)->toBe(ChangeDirection::Up);
});

it('reads a range nobody arrived in as a flat customers change', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->deliveredFulfillmentFor($seller), '2025-01-05 10:00:00');

    $tile = overviewTile(overviewTiles($seller), 'Customers');

    expect($tile->changeText)->toBe('+0 new')
        ->and($tile->changeDirection)->toBe(ChangeDirection::Flat);
});

it('counts the orders placed inside the range and leaves the earlier ones out', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter'])), '2026-09-01 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley'])), '2026-09-02 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Luna Lovegood'])), '2026-08-20 10:00:00');

    expect(overviewTile(overviewTiles($seller, days: 7), 'Orders')->value)->toBe('2')
        ->and(overviewTile(overviewTiles($seller, days: 30), 'Orders')->value)->toBe('3');
});

it('earns nothing from a parcel the seller declined and still counts the order', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $parcel = placedOn($this->paidFulfillmentFor($seller, priceCents: 10000), '2026-09-01 10:00:00');
    app(DeclineFulfillment::class)($parcel, 'The kiln cracked the glaze.', new DateTimeImmutable('2026-09-02 09:00:00'));

    $tiles = overviewTiles($seller, days: 7);

    expect(overviewTile($tiles, 'Earnings')->value)->toBe('$0.00')
        ->and(overviewTile($tiles, 'Orders')->value)->toBe('1');
});

it('gives every tile a line with one point per day of the range', function (int $days): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller), '2026-09-01 10:00:00');

    foreach (overviewTiles($seller, $days) as $tile) {
        expect(explode(' ', $tile->sparkline->points))->toHaveCount($days);
    }
})->with([
    'a week' => [7],
    'a month' => [30],
    'a quarter' => [90],
]);

it('names the parcels waiting to ship and the payout date in the footers', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller), '2026-09-01 10:00:00');

    $tiles = overviewTiles($seller);

    expect(overviewTile($tiles, 'Orders')->footerNote)->toBe('1 to ship')
        ->and(overviewTile($tiles, 'Earnings')->footerNote)->toStartWith('Next payout ');
});

it('reads the payout date off the estimate it is handed', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $now = overviewNow();
    $estimate = NextPayout::for($seller, $now)->estimate;

    $tiles = SellerOverview::for($seller, AnalyticsRange::of(30, $now), $estimate)->tiles();

    expect(overviewTile($tiles, 'Earnings')->footerNote)->toBe('Next payout '.$estimate->payoutDate->format('M j'));
});

it('leaves another sellers parcels out of every figure', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($this->seller('Ollivanders'), priceCents: 10000), '2026-09-01 10:00:00');

    $tiles = overviewTiles($seller);

    expect(overviewTile($tiles, 'Orders')->value)->toBe('0')
        ->and(overviewTile($tiles, 'Earnings')->value)->toBe('$0.00')
        ->and(overviewTile($tiles, 'Customers')->value)->toBe('0');
});

it('takes the payout estimate as given rather than reading a clock of its own', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $estimate = PayoutEstimate::from($seller->escrowBalance(), PayoutPeriod::containing(new DateTimeImmutable('2026-01-07 10:00:00')), 3);

    $tiles = SellerOverview::for($seller, AnalyticsRange::of(30, overviewNow()), $estimate)->tiles();

    expect(overviewTile($tiles, 'Earnings')->footerNote)->toBe('Next payout Jan 12');
});
