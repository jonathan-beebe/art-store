<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a quantity break below 2, or a discount outside 0.01 to 99.99 percent', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 1,
        'discount_percent' => '100',
    ]);

    $response->assertSessionHasErrors(['min_qty', 'discount_percent']);
});

it('refuses a discount with three decimal places', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 10,
        'discount_percent' => '12.555',
    ]);

    $response->assertSessionHasErrors('discount_percent');
});
