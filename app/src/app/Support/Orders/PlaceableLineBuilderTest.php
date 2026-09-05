<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Domain\Listings\ListingStatus;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Unit;
use App\Models\Variant;

it('builds a legacy cart lines placeable line off the live listing', function (): void {
    $listing = Listing::factory()->create(['title' => 'Harbour at Dusk', 'status' => ListingStatus::ForSale, 'quantity' => 3]);
    $item = CartItem::factory()->create(['listing_id' => $listing->id, 'quantity' => 1])->load('listing');

    $line = PlaceableLineBuilder::for($item);

    expect($line->lineId)->toBe($item->id)
        ->and($line->title)->toBe('Harbour at Dusk')
        ->and($line->availableQuantity)->toBe(3)
        ->and($line->configured)->toBeFalse();
});

it('reads a legacy order lines title off its own frozen snapshot, not the live listing', function (): void {
    $listing = Listing::factory()->create(['title' => 'Harbour at Dusk']);
    $item = OrderItem::factory()->create(['listing_id' => $listing->id, 'title' => 'Harbour at Dusk', 'quantity' => 1])->load('listing');
    $listing->update(['title' => 'Harbour at Dawn']);

    $line = PlaceableLineBuilder::for($item);

    expect($line->title)->toBe('Harbour at Dusk');
});

it('builds a configured lines placeable line off its variant', function (): void {
    $listing = Listing::factory()->create();
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => true, 'quantity' => 4]);
    $item = CartItem::factory()->create([
        'listing_id' => $listing->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ])->load('listing', 'variant');

    $line = PlaceableLineBuilder::for($item);

    expect($line->configured)->toBeTrue()
        ->and($line->variantEnabled)->toBeTrue()
        ->and($line->serialized)->toBeFalse()
        ->and($line->variantRemainingQuantity)->toBe(4);
});

it('reads a serialized lines specific unit availability', function (): void {
    $listing = Listing::factory()->create();
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->sold()->create(['variant_id' => $variant->id]);
    $item = CartItem::factory()->create([
        'listing_id' => $listing->id,
        'variant_id' => $variant->id,
        'unit_id' => $unit->id,
        'quantity' => 1,
    ])->load('listing', 'variant', 'unit');

    $line = PlaceableLineBuilder::for($item);

    expect($line->serialized)->toBeTrue()
        ->and($line->unitAvailable)->toBeFalse();
});
