<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\PricingMode;
use App\Models\OptionAxis;

it('refuses a missing name or an unknown catalog property', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => '',
        'property_id' => 'prp_does_not_exist',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors(['name', 'property_id']);
});

it('creates a new axis as add-on pricing when no pricing mode is sent', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Frame',
        'position' => 0,
    ]);

    expect(OptionAxis::where('listing_id', $listing->id)->sole()->pricing_mode)->toBe(PricingMode::AddOn);
});

it('changes an axis’s pricing mode when the request explicitly sends one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", [
        'name' => $axis->name,
        'position' => 0,
        'pricing_mode' => 'standalone',
    ]);

    expect($axis->refresh()->pricing_mode)->toBe(PricingMode::Standalone);
});

it('keeps an axis’s existing pricing mode on an update that sends none', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", [
        'name' => 'Renamed',
        'position' => 0,
    ]);

    expect($axis->refresh()->pricing_mode)->toBe(PricingMode::Standalone);
});
