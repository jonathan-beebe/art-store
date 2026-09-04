<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Orders\FinalizeOrder;

beforeEach(function (): void {
    $this->travelTo($this->moment('2026-08-19 09:00:00'));
});

it('refuses a signed-out visitor', function (): void {
    $this->get('/seller/earnings')->assertRedirect('/seller/login');
});

it('shows the next payout as the ledger balance available to pay out', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        priceCents: 10_000,
        orderedAt: $this->moment('2026-08-17 09:00:00'),
        shippedAt: $this->moment('2026-08-17 12:00:00'),
        deliveredAt: $this->moment('2026-08-18 10:00:00'),
    );

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee($seller->refresh()->escrowBalance()->available->format());
});

it('lists a held order with its buyer, item, and net', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['title' => 'Nine Owls']));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-18 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Nine Owls');
    $response->assertSee('Not yet shipped');
});

it('lists a shipped held order as in transit since it shipped', function (): void {
    $seller = $this->seller();
    $this->shippedFulfillmentFor($seller, orderedAt: $this->moment('2026-08-17 09:00:00'), shippedAt: $this->moment('2026-08-18 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('In transit since Aug 18');
});

it("shows this period's sale in the sales table", function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000, paidAt: $this->moment('2026-08-18 10:00:00'));
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-18 10:00:00')]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('$100.00');
    $response->assertSee('$90.00');
});

it('links a past period to its statement page', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Statement');
    $response->assertSee(route('seller.earnings.statements.show', '2026-08-10'), escape: false);
});
