<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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

    $response = $this->actingAs($this->admin(), 'admin')->post('/admin/payouts');

    $response->assertRedirect(route('admin.payouts.index'));
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

    $response = $this->actingAs($this->admin(), 'admin')->post('/admin/payouts');

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '1 payout(s)')
        && str_contains($status, '$90.00'));
});

it('flashes a zero count and total when nothing settled', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post('/admin/payouts');

    $response->assertRedirect(route('admin.payouts.index'));
    $response->assertSessionHas('status', 'Weekly payout run: 0 payout(s) totalling $0.00.');
    expect(Payout::count())->toBe(0);
});

it('pays out every seller with released escrow', function (): void {
    $blueKiln = $this->seller('Blue Kiln Studio');
    $ryePress = $this->seller('Rye Press');
    $this->deliveredFulfillmentFor(
        $blueKiln,
        orderedAt: $this->moment('2026-08-17 10:00:00'),
        trackingNumber: 'RM1',
        shippedAt: $this->moment('2026-08-18 10:00:00'),
        deliveredAt: $this->moment('2026-08-19 10:00:00'),
    );
    $this->deliveredFulfillmentFor(
        $ryePress,
        orderedAt: $this->moment('2026-08-17 10:00:00'),
        trackingNumber: 'RM2',
        shippedAt: $this->moment('2026-08-18 10:00:00'),
        deliveredAt: $this->moment('2026-08-19 10:00:00'),
    );

    $this->actingAs($this->admin(), 'admin')->post('/admin/payouts');

    expect(Payout::where('seller_id', $blueKiln->id)->exists())->toBeTrue()
        ->and(Payout::where('seller_id', $ryePress->id)->exists())->toBeTrue();
});

it('pays nothing again on a second run of the same period', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        orderedAt: $this->moment('2026-08-17 10:00:00'),
        trackingNumber: 'RM1',
        shippedAt: $this->moment('2026-08-18 10:00:00'),
        deliveredAt: $this->moment('2026-08-19 10:00:00'),
    );
    $this->actingAs($this->admin(), 'admin')->post('/admin/payouts');

    $this->actingAs($this->admin(), 'admin')->post('/admin/payouts');

    expect(Payout::count())->toBe(1);
});

it('settles the period named by an explicit as_of', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        orderedAt: $this->moment('2026-08-10 10:00:00'),
        trackingNumber: 'RM1',
        shippedAt: $this->moment('2026-08-11 10:00:00'),
        deliveredAt: $this->moment('2026-08-12 10:00:00'),
    );

    $response = $this->actingAs($this->admin(), 'admin')->post('/admin/payouts', ['as_of' => '2026-08-17']);

    $response->assertRedirect(route('admin.payouts.index'));
    $payout = Payout::where('seller_id', $seller->id)->sole();
    expect($payout->period_start->format('Y-m-d'))->toBe('2026-08-10');
});
