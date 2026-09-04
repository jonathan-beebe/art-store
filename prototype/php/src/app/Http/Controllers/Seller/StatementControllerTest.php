<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

beforeEach(function (): void {
    $this->travelTo($this->moment('2026-08-19 09:00:00'));
});

it('refuses a signed-out visitor', function (): void {
    $this->get('/seller/earnings/statements/2026-08-10')->assertRedirect('/seller/login');
});

it('shows the named period\'s figures and its sales', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000, paidAt: $this->moment('2026-08-11 10:00:00'));
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-11 10:00:00')]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/2026-08-10');

    $response->assertOk();
    $response->assertSee('2026-08-10 to 2026-08-16');
    $response->assertSee('$100.00');
});

it('answers 404 for a period outside the eight-period window', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/2020-01-06');

    $response->assertNotFound();
});

it('answers 404 for a malformed period', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/not-a-date');

    $response->assertNotFound();
});
