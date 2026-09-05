<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Actions\Orders\FinalizeOrder;

beforeEach(function (): void {
    $this->travelTo($this->moment('2026-08-19 09:00:00'));
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

it("shows this period's sale in the sales table, linked to its order", function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 10_000]));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-18 10:00:00'));
    $order->update(['placed_at' => $this->moment('2026-08-18 10:00:00')]);
    $fulfillment = $order->fulfillments()->sole();
    // Delivered, so it appears only in the sales table — not in the held-in-escrow list, which would
    // otherwise print the same buyer and item and leave the assertion unable to tell the two apart.
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123', $this->moment('2026-08-18 11:00:00'));
    app(ConfirmDelivered::class)($fulfillment->refresh(), $this->moment('2026-08-18 12:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Nine Owls');
    $response->assertSee(route('seller.orders.show', $fulfillment->id), escape: false);
});

it('shows the carried-balance badge once a refund after payout leaves the balance negative', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor(
        $seller,
        priceCents: 10_000,
        orderedAt: $this->moment('2026-08-11 09:00:00'),
        shippedAt: $this->moment('2026-08-11 12:00:00'),
        deliveredAt: $this->moment('2026-08-12 10:00:00'),
    );
    app(RunWeeklyPayout::class)($this->moment('2026-08-17 09:00:00'));
    app(RefundFulfillment::class)($fulfillment->refresh(), $this->admin(), 'Dispute resolved for the buyer.', $this->moment('2026-08-18 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Carried balance');
    expect($seller->refresh()->escrowBalance()->available->isPositive())->toBeFalse();
});

it('IMPRV-039 charts the net-per-period window through x-bar-strip, a loss period tinted and named by its sign', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->deliveredFulfillmentFor(
        $seller,
        priceCents: 10_000,
        orderedAt: $this->moment('2026-08-11 09:00:00'),
        shippedAt: $this->moment('2026-08-11 12:00:00'),
        deliveredAt: $this->moment('2026-08-12 10:00:00'),
    );
    // The sale belongs to the period it was placed in; only backdating it
    // there leaves the refunded period with nothing of its own to net it
    // against.
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-11 09:00:00')]);
    app(RunWeeklyPayout::class)($this->moment('2026-08-17 09:00:00'));
    app(RefundFulfillment::class)($fulfillment->refresh(), $this->admin(), 'Dispute resolved for the buyer.', $this->moment('2026-08-18 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');
    $content = (string) $response->getContent();

    $response->assertOk();
    expect($content)->toContain('<svg')
        ->and($content)->toContain('text-red-600 dark:text-red-500')
        ->and($content)->toMatch('/-\$[\d,]+\.\d\d, a net loss/')
        ->and($content)->not->toContain('bg-red-500');
});

it('links a past period to its statement page', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

    $response->assertOk();
    $response->assertSee('Statement');
    $response->assertSee(route('seller.earnings.statements.show', '2026-08-10'), escape: false);
});
