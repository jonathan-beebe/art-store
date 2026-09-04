<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\ChangeDirection;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
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

it('counts the new buyers by the rule the customers table counts them by', function (): void {
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

it('leaves an order nobody paid for out of the orders and earnings tiles', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000]));
    $order->update(['placed_at' => new DateTimeImmutable('2026-09-01 10:00:00')]);

    $tiles = overviewTiles($seller, days: 7);

    expect(overviewTile($tiles, 'Orders')->value)->toBe('0')
        ->and(overviewTile($tiles, 'Earnings')->value)->toBe('$0.00');
});

it('agrees with the earnings page on a parcel declined in a later period', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $parcel = placedOn($this->paidFulfillmentFor($seller, priceCents: 10000), '2026-08-18 10:00:00');
    app(DeclineFulfillment::class)($parcel->refresh(), 'Buyer changed their mind.', new DateTimeImmutable('2026-08-26 09:00:00'));

    $saleWeek = EarningsPeriods::for($seller, new DateTimeImmutable('2026-08-27 09:00:00'))->past()[0];

    // The dashboard's range is the sale's own payout week — the same week
    // the earnings page's period covers — so the two read the same net.
    $dashboardTiles = SellerOverview::for(
        $seller,
        AnalyticsRange::of(7, $saleWeek->period->end),
        NextPayout::for($seller, $saleWeek->period->end)->estimate,
    )->tiles();

    expect($saleWeek->net()->cents)->toBeGreaterThan(0)
        ->and(overviewTile($dashboardTiles, 'Earnings')->value)->toBe(Money::fromCents($saleWeek->net()->cents)->format());
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

it('reads the payout date off an estimate built against another period', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $estimate = PayoutEstimate::from($seller->escrowBalance(), PayoutPeriod::containing(new DateTimeImmutable('2026-01-07 10:00:00')), 3);

    $tiles = SellerOverview::for($seller, AnalyticsRange::of(30, overviewNow()), $estimate)->tiles();

    expect(overviewTile($tiles, 'Earnings')->footerNote)->toBe('Next payout Jan 12');
});

it('reads the orders count against the range before it', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    // A thirty-day range ending Sep 4 opens Aug 6; the range before it
    // opens Jul 7.
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter'])), '2026-08-25 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley'])), '2026-08-26 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Luna Lovegood'])), '2026-07-20 10:00:00');

    $tile = overviewTile(overviewTiles($seller), 'Orders');

    expect($tile->value)->toBe('2')
        ->and($tile->changeText)->toBe('+100.0%')
        ->and($tile->changeDirection)->toBe(ChangeDirection::Up);
});

it('reads a quieter range as an orders count down on the one before it', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter'])), '2026-08-25 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley'])), '2026-07-20 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Luna Lovegood'])), '2026-07-21 10:00:00');

    $tile = overviewTile(overviewTiles($seller), 'Orders');

    expect($tile->value)->toBe('1')
        ->and($tile->changeText)->toBe('−50.0%')
        ->and($tile->changeDirection)->toBe(ChangeDirection::Down);
});

it('reads a first range with nothing before it as new', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller, priceCents: 10000), '2026-08-25 10:00:00');

    $tiles = overviewTiles($seller);

    expect(overviewTile($tiles, 'Orders')->changeText)->toBe('new')
        ->and(overviewTile($tiles, 'Orders')->changeDirection)->toBe(ChangeDirection::Flat)
        ->and(overviewTile($tiles, 'Earnings')->changeText)->toBe('new')
        ->and(overviewTile($tiles, 'Earnings')->changeDirection)->toBe(ChangeDirection::Flat);
});

it('reads the earnings net against the net of the range before it', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter']), priceCents: 30000), '2026-08-25 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), priceCents: 10000), '2026-07-20 10:00:00');

    $tile = overviewTile(overviewTiles($seller), 'Earnings');

    expect($tile->value)->toBe('$270.00')
        ->and($tile->changeText)->toBe('+200.0%')
        ->and($tile->changeDirection)->toBe(ChangeDirection::Up);
});

it('reads a range earning the same as the one before it as level', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Harry Potter']), priceCents: 10000), '2026-08-25 10:00:00');
    placedOn($this->paidFulfillmentFor($seller, Customer::factory()->create(['name' => 'Ginny Weasley']), priceCents: 10000), '2026-07-20 10:00:00');

    $tile = overviewTile(overviewTiles($seller), 'Earnings');

    expect($tile->changeText)->toBe('0.0%')
        ->and($tile->changeDirection)->toBe(ChangeDirection::Flat);
});
