<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

it('shows a known article by its slug', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support/articles/printing-a-label-from-an-order');

    $response->assertOk();
    $response->assertSee('Printing a label from an order');
    $response->assertSee('asks for a carrier and tracking number', escape: false);
});

it('answers 404 for an unknown article slug', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support/articles/not-a-real-article');

    $response->assertNotFound();
});
