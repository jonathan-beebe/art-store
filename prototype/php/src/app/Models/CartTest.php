<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\UnavailableReason;

it('reads its items as cart lines', function (): void {
    $seller = $this->seller();
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($seller, ['price_cents' => 4500]);
    CartItem::create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'quantity' => 2]);

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

it('reads the customer it belongs to', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);

    expect($cart->customer()->sole()->is($customer))->toBeTrue();
});

it('plans placement from its items against the listings behind them', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'status' => ListingStatus::Archived]);
    CartItem::create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'quantity' => 1]);

    $plan = $cart->load('items.listing')->placementPlan();

    expect($plan->isPlaceable())->toBeFalse()
        ->and($plan->blocked[0]->title)->toBe('Harbour at Dawn')
        ->and($plan->blocked[0]->reason)->toBe(UnavailableReason::OffSale);
});
