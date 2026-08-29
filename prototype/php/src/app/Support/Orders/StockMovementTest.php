<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Domain\Configurator\UnitState;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\Unit;
use App\Models\Variant;

it('sells and restocks a legacy line off the listing', function (): void {
    $listing = Listing::factory()->create(['quantity' => 3]);
    $item = CartItem::factory()->create(['listing_id' => $listing->id, 'quantity' => 2])->load('listing');

    StockMovement::claim($item);
    expect($listing->refresh()->quantity)->toBe(1);

    StockMovement::release($item);
    expect($listing->refresh()->quantity)->toBe(3);
});

it('decrements and restores a non-serialized variant, leaving the listing untouched', function (): void {
    $listing = Listing::factory()->create(['quantity' => 1]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'quantity' => 5]);
    $item = CartItem::factory()->create([
        'listing_id' => $listing->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ])->load('listing', 'variant');

    StockMovement::claim($item);
    expect($variant->refresh()->quantity)->toBe(3)
        ->and($listing->refresh()->quantity)->toBe(1);

    StockMovement::release($item);
    expect($variant->refresh()->quantity)->toBe(5)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('sells and restocks a serialized lines specific unit, leaving the variant quantity untouched', function (): void {
    $listing = Listing::factory()->create();
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id]);
    $item = CartItem::factory()->create([
        'listing_id' => $listing->id,
        'variant_id' => $variant->id,
        'unit_id' => $unit->id,
        'quantity' => 1,
    ])->load('listing', 'variant', 'unit');

    StockMovement::claim($item);
    expect($unit->refresh()->state)->toBe(UnitState::Sold)
        ->and($variant->refresh()->quantity)->toBeNull();

    StockMovement::release($item);
    expect($unit->refresh()->state)->toBe(UnitState::Available);
});
