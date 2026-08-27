<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\OptionAxis;
use App\Models\OptionValue;

it('leaves price_cents alone for a listing with no standalone axis', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->surcharging(500)->default()->create(['axis_id' => $axis->id]);

    ListingPriceSync::sync($listing);

    expect($listing->refresh()->price_cents)->toBe(2000);
});

it('sets price_cents to the default option’s price on a standalone axis', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->priced(1800)->default()->create(['axis_id' => $axis->id, 'position' => 0]);
    OptionValue::factory()->priced(2400)->create(['axis_id' => $axis->id, 'position' => 1]);

    ListingPriceSync::sync($listing);

    expect($listing->refresh()->price_cents)->toBe(1800);
});

it('falls back to the first option by position when none is marked default', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->priced(3400)->create(['axis_id' => $axis->id, 'position' => 0]);
    OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id, 'position' => 1]);

    ListingPriceSync::sync($listing);

    expect($listing->refresh()->price_cents)->toBe(3400);
});

it('tracks a changed default option’s price', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    $eightByTen = OptionValue::factory()->priced(1800)->default()->create(['axis_id' => $axis->id, 'position' => 0]);
    OptionValue::factory()->priced(2400)->create(['axis_id' => $axis->id, 'position' => 1]);
    ListingPriceSync::sync($listing);

    $eightByTen->update(['price_cents' => 2000]);
    ListingPriceSync::sync($listing);

    expect($listing->refresh()->price_cents)->toBe(2000);
});

it('sums more than one standalone axis’s default price', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 0]);
    $size = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->priced(1800)->default()->create(['axis_id' => $size->id]);
    $material = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->priced(500)->default()->create(['axis_id' => $material->id]);

    ListingPriceSync::sync($listing);

    expect($listing->refresh()->price_cents)->toBe(2300);
});

it('leaves price_cents at its last synced value once the last standalone axis is removed', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->priced(1800)->default()->create(['axis_id' => $axis->id]);
    ListingPriceSync::sync($listing);
    expect($listing->refresh()->price_cents)->toBe(1800);

    // The axis is gone — nothing here re-derives price_cents back toward the
    // $2000 the listing started at; it simply stops being written to.
    $axis->delete();
    ListingPriceSync::sync($listing);

    expect($listing->refresh()->price_cents)->toBe(1800);
});
