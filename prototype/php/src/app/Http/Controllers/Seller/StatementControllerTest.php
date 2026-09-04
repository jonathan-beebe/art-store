<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\DeclineFulfillment;

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

it('reconciles its net with the ledger fold for a sale refunded in its own period', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000, paidAt: $this->moment('2026-08-11 10:00:00'));
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-11 10:00:00')]);
    app(DeclineFulfillment::class)($fulfillment->refresh(), 'Buyer changed their mind.', $this->moment('2026-08-12 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/2026-08-10');

    $response->assertOk();
    // Sales $100.00, fees $10.00, refunds $90.00 (the net IssueRefund sends back) — the period's own net is $0.00,
    // the same figure the ledger fold reaches for this seller once the refund has settled.
    $response->assertSeeInOrder(['$100.00', '$10.00', '$90.00', '$0.00']);
    expect($seller->refresh()->escrowBalance()->available->isZero())->toBeTrue()
        ->and($seller->escrowBalance()->held->isZero())->toBeTrue();
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

it('IMPRV-030 offers a print control that still reads with scripts blocked', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/2026-08-10');
    $content = (string) $response->getContent();

    $response->assertOk();
    expect($content)->toContain('data-print')
        ->toContain('<script defer src="'.asset('print-button.js').'"')
        ->toContain('<noscript>');
});

it('IMPRV-030 prints the seller name and net legibly in dark mode', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/2026-08-10');
    $content = (string) $response->getContent();

    $response->assertOk();
    expect($content)->toContain('dark:text-white print:dark:text-black');
});
