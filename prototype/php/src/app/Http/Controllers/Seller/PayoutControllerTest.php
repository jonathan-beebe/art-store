<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Payout;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-24 09:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('pays out the released escrow of the last completed week', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        orderedAt: $this->moment('2026-08-17 10:00:00'),
        trackingNumber: 'RM1',
        shippedAt: $this->moment('2026-08-18 10:00:00'),
        deliveredAt: $this->moment('2026-08-19 10:00:00'),
    );

    $response = $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

    $response->assertRedirect(route('seller.earnings'));
    $payout = Payout::where('seller_id', $seller->id)->sole();
    expect($payout->amount_cents)->toBe(9000);
});

it('flashes the count and the amount', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        orderedAt: $this->moment('2026-08-17 10:00:00'),
        trackingNumber: 'RM1',
        shippedAt: $this->moment('2026-08-18 10:00:00'),
        deliveredAt: $this->moment('2026-08-19 10:00:00'),
    );

    $response = $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '1 payout(s)')
        && str_contains($status, '$90.00'));
});

it('pays nobody when nothing was released', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '0 payout(s)')
        && str_contains($status, '$0.00'));
    expect(Payout::count())->toBe(0);
});

it('pays nothing again on a second run of the same week', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        orderedAt: $this->moment('2026-08-17 10:00:00'),
        trackingNumber: 'RM1',
        shippedAt: $this->moment('2026-08-18 10:00:00'),
        deliveredAt: $this->moment('2026-08-19 10:00:00'),
    );
    $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

    $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

    expect(Payout::count())->toBe(1);
});
