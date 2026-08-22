<?php

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Cart;
use App\Models\Customer;
use DomainException;
use Tests\CommerceTestCase;

final class PlaceOrderTest extends CommerceTestCase
{
    public function test_it_turns_the_cart_into_an_order_the_customer_can_pay_for(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartWithOneListing($customer, 45000);

        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(45000, $order->subtotal_cents);
        $this->assertSame(45000, $order->total_cents);
        $this->assertSame('2026-08-20 09:00:00', $order->placed_at->format('Y-m-d H:i:s'));
        $this->assertNull($order->finalized_at);
    }

    public function test_an_unverified_customer_places_an_order_that_waits_for_verification(): void
    {
        $customer = $this->anonymousCustomer();
        $cart = $this->cartWithOneListing($customer, 45000);

        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $this->assertSame(OrderStatus::PendingVerification, $order->status);
    }

    public function test_it_copies_the_shipping_address_onto_the_order(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartWithOneListing($customer, 45000);

        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $this->assertSame('Ada Lovelace', $order->shipping_name);
        $this->assertSame('12 Analytical Way', $order->shipping_line1);
        $this->assertNull($order->shipping_line2);
        $this->assertSame('EC1A 1BB', $order->shipping_postal_code);
    }

    public function test_it_snapshots_the_title_and_price_of_every_item(): void
    {
        $customer = $this->verifiedCustomer();
        $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dusk', 'price_cents' => 45000]);
        $cart = $this->cartFor($customer);
        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $item = $order->items()->sole();
        $this->assertSame('Harbour at Dusk', $item->title);
        $this->assertSame(45000, $item->unit_price_cents);
        $this->assertSame($listing->seller_id, $item->seller_id);
    }

    public function test_it_splits_the_order_into_one_fulfillment_per_seller(): void
    {
        $customer = $this->verifiedCustomer();
        $first = $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]);
        $second = $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]);
        $cart = $this->cartFor($customer);
        $addToCart = app(AddToCart::class);
        $addToCart($cart, $first, 1, $this->moment('2026-08-20 08:00:00'));
        $addToCart($cart, $second, 1, $this->moment('2026-08-20 08:00:00'));

        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $this->assertSame(55000, $order->subtotal_cents);
        $this->assertSame(
            [
                [$first->seller_id, 45000, 4500, 40500],
                [$second->seller_id, 10000, 1000, 9000],
            ],
            $order->fulfillments()->orderBy('seller_id')->get()
                ->map(fn ($fulfillment) => [
                    $fulfillment->seller_id,
                    $fulfillment->subtotal_cents,
                    $fulfillment->fee_cents,
                    $fulfillment->net_cents,
                ])->all(),
        );
    }

    public function test_every_fulfillment_starts_awaiting_shipment(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartWithOneListing($customer, 45000);

        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $this->assertSame(FulfillmentStatus::AwaitingShipment, $order->fulfillments()->sole()->status);
    }

    public function test_it_takes_the_stock_the_order_claims(): void
    {
        $customer = $this->verifiedCustomer();
        $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 3]);
        $cart = $this->cartFor($customer);
        app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'));

        app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $listing->refresh();
        $this->assertSame(1, $listing->quantity);
        $this->assertSame(ListingStatus::ForSale, $listing->status);
    }

    public function test_the_last_of_a_listing_marks_it_sold(): void
    {
        $customer = $this->verifiedCustomer();
        $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
        $cart = $this->cartFor($customer);
        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

        app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $listing->refresh();
        $this->assertSame(0, $listing->quantity);
        $this->assertSame(ListingStatus::Sold, $listing->status);
    }

    public function test_it_empties_the_cart(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartWithOneListing($customer, 45000);

        app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $this->assertSame(0, $cart->items()->count());
    }

    public function test_it_refuses_an_empty_cart(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartFor($customer);

        $this->expectException(DomainException::class);

        app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    }

    private function cartWithOneListing(Customer $customer, int $priceCents): Cart
    {
        $cart = $this->cartFor($customer);
        $listing = $this->listing($this->seller(), ['price_cents' => $priceCents]);
        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

        return $cart;
    }
}
