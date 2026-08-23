<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingEventType;
use App\Models\CustomerBlock;
use App\Models\ListingEvent;
use DomainException;

it('puts a listing in the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller(), ['quantity' => 3]);

    $item = app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 09:00:00'));

    expect($item->listing_id)->toBe($listing->id)
        ->and($item->quantity)->toBe(2);
});

it('raises the quantity on one line when the same listing is added twice', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 5]);
    $addToCart = app(AddToCart::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $addToCart($cart, $listing, 1, $now);
    $item = $addToCart($cart, $listing, 2, $now);

    expect($item->quantity)->toBe(3)
        ->and($cart->items()->count())->toBe(1);
});

it('caps the quantity at the stock the listing has left', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 2]);

    $item = app(AddToCart::class)($cart, $listing, 9, $this->moment('2026-08-20 09:00:00'));

    expect($item->quantity)->toBe(2);
});

it('refuses a sold out listing', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['quantity' => 0]);

    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));
})->throws(DomainException::class);

it('refuses a blocked customer', function (): void {
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Chargeback fraud.']);
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller());

    $add = fn () => app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));

    expect($add)->toThrow(DomainRuleViolation::class, 'Chargeback fraud.')
        ->and($cart->items()->count())->toBe(0);
});

it('records the add as a listing event', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($this->seller());

    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));

    $event = ListingEvent::query()->sole();

    expect($event->type)->toBe(ListingEventType::CartAdd)
        ->and($event->customer_id)->toBe($customer->id);
});
