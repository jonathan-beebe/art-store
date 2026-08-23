<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Cart\AddToCart;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base for the commerce action tests: an in-memory database plus the handful of
 * rows every order lifecycle test starts from.
 */
abstract class CommerceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function seller(string $shopName = 'Blue Kiln Studio'): Seller
    {
        return Seller::factory()->create(['shop_name' => $shopName]);
    }

    protected function verifiedCustomer(): Customer
    {
        return Customer::factory()->create();
    }

    protected function anonymousCustomer(): Customer
    {
        return Customer::factory()->anonymous()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function listing(Seller $seller, array $attributes = []): Listing
    {
        return Listing::factory()->create($attributes + ['seller_id' => $seller->id]);
    }

    protected function purchaser(Customer $customer): Purchaser
    {
        return new Purchaser(
            $customer->id,
            $customer->email,
            $customer->email_verified_at?->toDateTimeImmutable(),
        );
    }

    protected function cartFor(Customer $customer): Cart
    {
        return Cart::create(['customer_id' => $customer->id]);
    }

    /**
     * A cart holding one listing at the given price, added by the given customer.
     */
    protected function cartWithOneListing(Customer $customer, int $priceCents): Cart
    {
        $cart = $this->cartFor($customer);
        $listing = $this->listing($this->seller(), ['price_cents' => $priceCents]);
        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

        return $cart;
    }

    /**
     * Walks a customer through the storefront up to the point of payment.
     */
    protected function orderFor(Customer $customer, Listing ...$listings): Order
    {
        $cart = $this->cartFor($customer);
        $addToCart = app(AddToCart::class);

        foreach ($listings as $listing) {
            $addToCart($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));
        }

        return app(PlaceOrder::class)(
            $cart,
            $this->purchaser($customer),
            $this->shippingAddress(),
            $this->moment('2026-08-20 09:00:00'),
        );
    }

    /**
     * A paid order split across two sellers, one fulfillment each.
     */
    protected function paidOrderWithTwoSellers(): Order
    {
        $order = $this->orderFor(
            $this->verifiedCustomer(),
            $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]),
            $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]),
        );

        return app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    }

    protected function shippingAddress(): ShippingAddress
    {
        return ShippingAddress::to(
            name: 'Ada Lovelace',
            line1: '12 Analytical Way',
            line2: null,
            city: 'London',
            region: 'Greater London',
            postalCode: 'EC1A 1BB',
            country: 'GB',
        );
    }

    protected function moment(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when);
    }

    /**
     * Carries a listing through checkout, payment, and shipment for the given
     * seller. Shared by every test that needs a fulfillment already in transit.
     */
    protected function shippedFulfillmentFor(
        Seller $seller,
        ?Customer $customer = null,
        int $priceCents = 10000,
        string $carrier = 'Royal Mail',
        string $trackingNumber = 'RM123',
        ?DateTimeImmutable $orderedAt = null,
        ?DateTimeImmutable $shippedAt = null,
    ): Fulfillment {
        $order = $this->orderFor(
            $customer ?? $this->verifiedCustomer(),
            $this->listing($seller, ['price_cents' => $priceCents]),
        );
        app(FinalizeOrder::class)($order, '4242424242424242', $orderedAt ?? $this->moment('2026-08-20 10:00:00'));
        $fulfillment = $order->fulfillments()->sole();

        return app(MarkShipped::class)($fulfillment, $carrier, $trackingNumber, $shippedAt ?? $this->moment('2026-08-21 10:00:00'));
    }

    /**
     * As {@see self::shippedFulfillmentFor()}, carried one step further to delivered.
     */
    protected function deliveredFulfillmentFor(
        Seller $seller,
        ?Customer $customer = null,
        int $priceCents = 10000,
        string $carrier = 'Royal Mail',
        string $trackingNumber = 'RM123',
        ?DateTimeImmutable $orderedAt = null,
        ?DateTimeImmutable $shippedAt = null,
        ?DateTimeImmutable $deliveredAt = null,
    ): Fulfillment {
        $fulfillment = $this->shippedFulfillmentFor($seller, $customer, $priceCents, $carrier, $trackingNumber, $orderedAt, $shippedAt);

        return app(ConfirmDelivered::class)($fulfillment->fresh(), $deliveredAt ?? $this->moment('2026-08-22 10:00:00'));
    }
}
