<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Cart\AddToCart;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Models\Cart;
use App\Models\Customer;
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

    protected function shippingAddress(): ShippingAddress
    {
        return new ShippingAddress(
            'Ada Lovelace',
            '12 Analytical Way',
            null,
            'London',
            'Greater London',
            'EC1A 1BB',
            'GB',
        );
    }

    protected function moment(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when);
    }
}
