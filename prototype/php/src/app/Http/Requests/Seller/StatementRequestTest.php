<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

beforeEach(function (): void {
    $this->travelTo($this->moment('2026-08-19 09:00:00'));
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

it('shows a period any seller charts, whether or not they sold anything in it', function (): void {
    $seller = $this->seller('Rye Press');

    $response = $this->actingAs($seller, 'seller')->get('/seller/earnings/statements/2026-08-10');

    $response->assertOk();
});
