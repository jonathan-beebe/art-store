<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\CartLineFingerprint;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\UnavailableReason;

it('reads its items as cart lines', function (): void {
    $seller = $this->seller();
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($seller, ['price_cents' => 4500]);
    CartItem::factory()->create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'quantity' => 2]);

    $lines = $cart->lines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->sellerId)->toBe($seller->id)
        ->and($lines[0]->unitPrice)->toBeMoney(4500)
        ->and($lines[0]->quantity)->toBe(2);
});

it('reads no lines for an empty cart', function (): void {
    $cart = $this->cartFor($this->anonymousCustomer());

    expect($cart->lines())->toBe([]);
});

it('blocks a line the placement plan finds unavailable', function (string $reasonKind, UnavailableReason $expectedReason): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);

    $listing = match ($reasonKind) {
        'off_sale' => $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'status' => ListingStatus::Archived]),
        'removed' => $this->listing($this->seller(), ['title' => 'Winter Elm']),
        'sold_out' => $this->listing($this->seller(), ['title' => 'Line Art Cat Tee', 'quantity' => 50]),
    };

    $variantId = null;

    if ($reasonKind === 'removed') {
        ListingRemoval::factory()->create(['listing_id' => $listing->id]);
    }

    if ($reasonKind === 'sold_out') {
        $variantId = Variant::factory()->create(['listing_id' => $listing->id, 'quantity' => 0])->id;
    }

    CartItem::factory()->create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'variant_id' => $variantId, 'quantity' => 1]);

    $plan = $cart->load('items.listing', 'items.variant')->placementPlan();

    expect($plan->isPlaceable())->toBeFalse()
        ->and($plan->blocked[0]->title)->toBe($listing->title)
        ->and($plan->blocked[0]->reason)->toBe($expectedReason);
})->with([
    'off sale' => ['off_sale', UnavailableReason::OffSale],
    'removed even while for sale' => ['removed', UnavailableReason::Removed],
    'sold out via its variant rather than the listing quantity' => ['sold_out', UnavailableReason::SoldOut],
]);

it('tells two configurations of the same listing apart when only one is blocked', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller(), ['title' => 'Line Art Cat Tee']);
    $available = Variant::factory()->create(['listing_id' => $listing->id, 'quantity' => 3]);
    $soldOut = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'other', 'quantity' => 0]);
    $keptItem = CartItem::factory()->create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'variant_id' => $available->id,
        'quantity' => 1,
        'fingerprint' => CartLineFingerprint::of($available->id, null, [])->value,
    ]);
    $blockedItem = CartItem::factory()->create([
        'cart_id' => $cart->id,
        'listing_id' => $listing->id,
        'variant_id' => $soldOut->id,
        'quantity' => 1,
        'fingerprint' => CartLineFingerprint::of($soldOut->id, null, [])->value,
    ]);

    $plan = $cart->load('items.listing', 'items.variant')->placementPlan();

    expect($plan->blockedReasonFor($keptItem->id))->toBeNull()
        ->and($plan->blockedReasonFor($blockedItem->id))->toBe(UnavailableReason::SoldOut);
});
