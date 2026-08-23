<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingEventType;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;

it('is anonymous when it has no email', function (): void {
    expect((new Customer)->isAnonymous())->toBeTrue();
});

it('is not anonymous once it has an email', function (): void {
    $customer = new Customer(['email' => 'shopper@example.com']);

    expect($customer->isAnonymous())->toBeFalse();
});

it('is verified once its address is confirmed', function (): void {
    expect($this->verifiedCustomer()->isVerified())->toBeTrue()
        ->and($this->anonymousCustomer()->isVerified())->toBeFalse();
});

it('reads the orders it placed', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    expect($customer->orders()->pluck('id')->all())->toBe([$order->id]);
});

it('reads the carts it filled', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $this->cartFor($this->anonymousCustomer());

    expect($customer->carts()->pluck('id')->all())->toBe([$cart->id]);
});

it('reads its favorites and the listings behind them', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    $this->listing($this->seller());
    Favorite::create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);

    expect($customer->favorites()->count())->toBe(1)
        ->and($customer->favoriteListings()->pluck('listings.id')->all())->toBe([$listing->id]);
});

it('reads the notifications addressed to it', function (): void {
    $customer = $this->verifiedCustomer();
    Notification::to(RecipientType::Customer, $customer->id, NotificationMessage::orderShipped(4, 'USPS', '94001'));
    Notification::to(RecipientType::Seller, $this->seller()->id, NotificationMessage::orderShipped(5, 'USPS', '94002'));

    expect($customer->notifications()->count())->toBe(1)
        ->and($customer->notifications()->unread()->count())->toBe(1);
});

it('reads the listing events it left', function (): void {
    $customer = $this->anonymousCustomer();
    ListingEvent::create([
        'listing_id' => $this->listing($this->seller())->id,
        'customer_id' => $customer->id,
        'type' => ListingEventType::View,
        'occurred_at' => $this->moment('2026-08-20 09:00:00'),
    ]);

    expect($customer->listingEvents()->count())->toBe(1);
});

it('gives a customer without a cart one', function (): void {
    $customer = $this->anonymousCustomer();

    $cart = $customer->currentCart();

    expect($cart->customer_id)->toBe($customer->id)
        ->and(Cart::count())->toBe(1);
});

it('returns the same cart twice', function (): void {
    $customer = $this->anonymousCustomer();

    expect($customer->currentCart()->id)->toBe($customer->currentCart()->id);
});

it('picks the cart holding the items after a merge', function (): void {
    $customer = $this->verifiedCustomer();
    $this->cartFor($customer);
    $filled = $this->cartFor($customer);
    CartItem::create([
        'cart_id' => $filled->id,
        'listing_id' => $this->listing($this->seller())->id,
        'quantity' => 1,
    ]);

    expect($customer->currentCart()->id)->toBe($filled->id);
});
