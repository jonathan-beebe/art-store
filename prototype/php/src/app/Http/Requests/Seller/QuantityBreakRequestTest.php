<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Models\QuantityBreak;

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

it('accepts a minimum quantity of exactly 2', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 2,
        'discount_percent' => '10',
    ]);

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    $response->assertSessionHasNoErrors();
});

it('refuses an eleventh tier with its own message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($i = 0; $i < ConfiguratorPublishValidation::MAX_QUANTITY_TIERS; $i++) {
        QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 2 + $i, 'discount_bps' => 100]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 50,
        'discount_percent' => '1',
    ]);

    $response->assertSessionHasErrors([
        'min_qty' => 'This listing already holds '.ConfiguratorPublishValidation::MAX_QUANTITY_TIERS.' quantity tiers, the most allowed.',
    ]);
    expect(QuantityBreak::where('listing_id', $listing->id)->count())->toBe(ConfiguratorPublishValidation::MAX_QUANTITY_TIERS);
});
