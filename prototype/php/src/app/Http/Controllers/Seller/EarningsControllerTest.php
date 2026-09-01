<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Escrow\LedgerBalance;
use App\Models\Fulfillment;
use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Collection;

$paidFulfillment = function (Seller $seller, int $priceCents, string $title = 'Harbour at Dusk'): Fulfillment {
    $order = test()->orderFor(
        test()->verifiedCustomer(),
        test()->listing($seller, ['price_cents' => $priceCents, 'title' => $title]),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));

    return Fulfillment::where('seller_id', $seller->id)->sole();
};

it('renders the earnings page', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Earnings');
});

it('offers no control that runs a payout', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertDontSee('Run weekly payout');
    $response->assertDontSee(route('admin.payouts.run'), escape: false);
});

it('reports the subtotal fee and net of each sale', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller, 10000);

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertSee('$100.00');
    $response->assertSee('$10.00');
    $response->assertSee('$90.00');
});

it('keeps another sellers sales off the report', function () use ($paidFulfillment): void {
    $paidFulfillment($this->seller('Other Studio'), 10000, 'Not Mine');

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/earnings');

    $response->assertDontSee('Not Mine');
});

it('holds a paid sale in escrow', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $paidFulfillment($seller, 10000);

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertViewHas('balance', fn (LedgerBalance $balance): bool => $balance->held->cents === 9000 && $balance->available->cents === 0);
});

it('moves a delivered sale to available', function () use ($paidFulfillment): void {
    $seller = $this->seller();
    $fulfillment = $paidFulfillment($seller, 10000);
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM1', $this->moment('2026-08-21 10:00:00'));
    app(ConfirmDelivered::class)($fulfillment->refresh(), $this->moment('2026-08-22 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertViewHas('balance', fn (LedgerBalance $balance): bool => $balance->available->cents === 9000);
});

it('lists the payouts of this seller only', function (): void {
    $seller = $this->seller();
    Payout::factory()->create([
        'seller_id' => $seller->id,
        'period_start' => '2026-08-10',
        'period_end' => '2026-08-16',
        'amount_cents' => 9000,
        'paid_at' => '2026-08-17 00:00:00',
    ]);
    Payout::factory()->create([
        'seller_id' => $this->seller('Other Studio')->id,
        'period_start' => '2026-08-10',
        'period_end' => '2026-08-16',
        'amount_cents' => 4200,
        'paid_at' => '2026-08-17 00:00:00',
    ]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertViewHas('payouts', fn (Collection $payouts): bool => $payouts->count() === 1);
    $response->assertSee('Aug 10, 2026');
    $response->assertDontSee('$42.00');
});

it('renders on a fixed number of queries however many entries the ledger holds', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller, priceCents: 10000, trackingNumber: 'RM1');
    $this->deliveredFulfillmentFor($seller, priceCents: 20000, trackingNumber: 'RM2');
    $this->deliveredFulfillmentFor($seller, priceCents: 30000, trackingNumber: 'RM3');

    $response = $this->actingAs($seller, 'seller')
        // +1 for the page-view roll-up's upsert, which runs after every
        // countable response (RollUpPageViews); +2 for the seller layout's
        // awaiting-shipment count and unread-notifications check.
        ->expectsDatabaseQueryCount(10)
        ->get('/seller/earnings');

    $response->assertOk();
});

it('says so when nothing has been refunded', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('No refunds.');
});

it('lists the refunded movements taken back out of escrow', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    app(RefundFulfillment::class)($fulfillment, $this->admin(), 'Dispute settled.', $this->moment('2026-08-23 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Refunded');
    $response->assertSee('-$90.00');
    $response->assertSee($fulfillment->order_id);
});
