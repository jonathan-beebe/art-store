<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\CustomerBlock;
use App\Models\Order;
use DomainException;

it('turns the cart into an order the customer can pay for', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->subtotal_cents)->toBe(45000)
        ->and($order->total_cents)->toBe(45000)
        ->and($order->finalized_at)->toBeNull()
        ->and($order->placed_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('places an order that waits for verification for an unverified customer', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->status)->toBe(OrderStatus::PendingVerification);
});

it('copies the shipping address onto the order', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->shipping_name)->toBe('Ada Lovelace')
        ->and($order->shipping_line1)->toBe('12 Analytical Way')
        ->and($order->shipping_line2)->toBeNull()
        ->and($order->shipping_postal_code)->toBe('EC1A 1BB');
});

it('snapshots the title and price of every item', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dusk', 'price_cents' => 45000]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $item = $order->items()->sole();

    expect($item->title)->toBe('Harbour at Dusk')
        ->and($item->unit_price_cents)->toBe(45000)
        ->and($item->seller_id)->toBe($listing->seller_id);
});

it('splits the order into one fulfillment per seller', function (): void {
    $customer = $this->verifiedCustomer();
    $first = $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]);
    $second = $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]);
    $cart = $this->cartFor($customer);
    $addToCart = app(AddToCart::class);
    $addToCart($cart, $first, 1, $this->moment('2026-08-20 08:00:00'));
    $addToCart($cart, $second, 1, $this->moment('2026-08-20 08:00:00'));

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->subtotal_cents)->toBe(55000);
    expect(
        $order->fulfillments()->orderBy('seller_id')->get()
            ->map(fn ($fulfillment) => [
                $fulfillment->seller_id,
                $fulfillment->subtotal_cents,
                $fulfillment->fee_cents,
                $fulfillment->net_cents,
            ])->all(),
    )->toBe([
        [$first->seller_id, 45000, 4500, 40500],
        [$second->seller_id, 10000, 1000, 9000],
    ]);
});

it('starts every fulfillment awaiting shipment', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($order->fulfillments()->sole()->status)->toBe(FulfillmentStatus::AwaitingShipment);
});

it('takes the stock the order claims', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 3]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'));

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $listing->refresh();

    expect($listing->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('marks a listing sold when the order claims the last of it', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    $listing->refresh();

    expect($listing->quantity)->toBe(0)
        ->and($listing->status)->toBe(ListingStatus::Sold);
});

it('empties the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($cart->items()->count())->toBe(0);
});

it('refuses an empty cart', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);

    app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
})->throws(DomainException::class);

it('refuses a listing that left the storefront while it sat in the cart', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 45000]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $listing->update(['status' => ListingStatus::Archived]);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(DomainRuleViolation::class, '“Harbour at Dawn” is no longer for sale.')
        ->and(Order::count())->toBe(0)
        ->and($cart->items()->count())->toBe(1)
        ->and($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::Archived);
});

it('refuses a blocked customer', function (): void {
    $customer = $this->verifiedCustomer();
    $cart = $this->cartWithOneListing($customer, 45000);
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Chargeback fraud.']);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(DomainRuleViolation::class, 'Chargeback fraud.')
        ->and(Order::count())->toBe(0);
});

it('refuses a listing whose last unit sold to someone else', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 45000, 'quantity' => 1]);
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $this->orderFor($this->verifiedCustomer(), $listing);

    $place = fn () => app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($place)->toThrow(DomainRuleViolation::class, '“Winter Elm” is no longer for sale.')
        ->and(Order::count())->toBe(1);
});
