<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Fulfillment;
use App\Notifications\ItemSold;

it('renders the seller dashboard', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('links the built stylesheet', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller');

    $response->assertSee('/build/assets/', escape: false);
});

it('shows a flashed magic link in the debug alert', function (): void {
    $link = 'http://localhost:8000/auth/magic/abc123';

    $response = $this->actingAs($this->seller(), 'seller')
        ->withSession(['debug_magic_link' => $link])
        ->get('/seller');

    $response->assertSee($link, escape: false);
});

it('counts the sellers listings by status', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::ForSale]);
    $this->listing($seller, ['status' => ListingStatus::ForSale]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('tally', function (array $tally): bool {
        return $tally[0]->count === 1 && $tally[1]->count === 2 && $tally[2]->count === 0;
    });
});

it('leaves another sellers listings out of the counts', function (): void {
    $seller = $this->seller();
    $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::ForSale]);

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('tally', fn (array $tally): bool => $tally[1]->count === 0);
});

it('counts the fulfillments awaiting shipment', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('openFulfillments', 1);
});

it('holds the net of a paid order in escrow', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000]));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertSee('$90.00');
});

it('makes a delivered order available', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000]));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM1', $this->moment('2026-08-21 10:00:00'));
    app(ConfirmDelivered::class)($fulfillment->fresh(), $this->moment('2026-08-22 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('balance', fn ($balance): bool => $balance->available->cents === 9000 && $balance->held->cents === 0);
});

it('counts unread notifications', function (): void {
    $seller = $this->seller();
    $seller->notify(new ItemSold(41, Money::fromCents(9000)));
    $seller->notify(new ItemSold(42, Money::fromCents(9000)));
    $seller->notifications()->firstOrFail()->markAsRead();

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('unreadNotifications', 1);
});

it('shows the five most recent notifications', function (): void {
    $seller = $this->seller();
    $this->freezeTime();
    foreach (range(1, 6) as $number) {
        $this->travel(1)->minutes();
        $seller->notify(new ItemSold($number, Money::fromCents(9000)));
    }

    $response = $this->actingAs($seller, 'seller')->get('/seller');

    $response->assertViewHas('notifications', fn ($notifications): bool => $notifications->count() === 5);
    $response->assertSee('Order #6 is paid.');
    $response->assertDontSee('Order #1 is paid.');
});

it('renders on a fixed number of queries however many rows the seller holds', function (): void {
    $seller = $this->seller();
    foreach (range(1, 6) as $number) {
        $this->listing($seller, ['status' => ListingStatus::ForSale, 'title' => "Print {$number}"]);
    }
    $this->deliveredFulfillmentFor($seller, priceCents: 10000);

    $response = $this->actingAs($seller, 'seller')
        ->expectsDatabaseQueryCount(5)
        ->get('/seller');

    $response->assertOk();
});
