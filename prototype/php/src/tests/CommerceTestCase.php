<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Cart\AddToCart;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Store\StartStore;
use App\Domain\Configurator\PropertyDataType;
use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\ListingImage;
use App\Models\Order;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\Seller;
use App\Models\StoreProfile;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base for the commerce action tests: an in-memory database plus the handful of
 * rows every order lifecycle test starts from.
 */
abstract class CommerceTestCase extends TestCase
{
    use RefreshDatabase;

    public function seller(string $shopName = 'Blue Kiln Studio'): Seller
    {
        return Seller::factory()->create(['shop_name' => $shopName]);
    }

    /**
     * The admin behind `actingAs($this->admin(), 'admin')`, the way every
     * seller-portal test signs in with `actingAs($this->seller(), 'seller')`.
     */
    public function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function verifiedCustomer(): Customer
    {
        return Customer::factory()->create();
    }

    public function anonymousCustomer(): Customer
    {
        return Customer::factory()->anonymous()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function listing(Seller $seller, array $attributes = []): Listing
    {
        return Listing::factory()->create($attributes + ['seller_id' => $seller->id]);
    }

    /**
     * A listing attribute for the given property name and value label —
     * `firstOrCreate` on the property so two calls in the same test share
     * one property, the way a category can grant it more than once.
     */
    public function attribute(Listing $listing, string $propertyName, string $label): ListingAttribute
    {
        $property = Property::firstOrCreate(['name' => $propertyName], ['data_type' => PropertyDataType::Enum]);
        $value = PropertyValue::firstOrCreate(['property_id' => $property->id, 'label' => $label]);

        return ListingAttribute::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'property_id' => $property->id,
            'property_value_id' => $value->id,
        ]);
    }

    /**
     * The storefront's Medium attribute, the shorthand every media-filter
     * test reaches for.
     */
    public function mediumAttribute(Listing $listing, string $label): ListingAttribute
    {
        return $this->attribute($listing, 'Medium', $label);
    }

    /**
     * A stored image row on the given listing — the shorthand every test
     * that needs a listing to already carry a cover or extra photo reaches
     * for, since a factory-built listing starts with none.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function listingImage(Listing $listing, array $attributes = []): ListingImage
    {
        return ListingImage::factory()->create($attributes + ['listing_id' => $listing->id]);
    }

    public function purchaser(Customer $customer): Purchaser
    {
        return Purchaser::onAccount(
            $customer->id,
            $customer->email,
            $customer->email_verified_at?->toDateTimeImmutable(),
        );
    }

    public function cartFor(Customer $customer): Cart
    {
        return Cart::create(['customer_id' => $customer->id]);
    }

    /**
     * A cart holding one listing at the given price, added by the given customer.
     */
    public function cartWithOneListing(Customer $customer, int $priceCents): Cart
    {
        $cart = $this->cartFor($customer);
        $listing = $this->listing($this->seller(), ['price_cents' => $priceCents]);
        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'));

        return $cart;
    }

    /**
     * Walks a customer through the storefront up to the point of payment.
     */
    public function orderFor(Customer $customer, Listing ...$listings): Order
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
     * Carries one listing through checkout and payment for the given seller,
     * and hands back the fulfillment waiting to be shipped.
     */
    public function paidFulfillmentFor(
        Seller $seller,
        ?Customer $customer = null,
        int $priceCents = 10000,
        ?DateTimeImmutable $paidAt = null,
    ): Fulfillment {
        $order = $this->orderFor(
            $customer ?? $this->verifiedCustomer(),
            $this->listing($seller, ['price_cents' => $priceCents]),
        );
        app(FinalizeOrder::class)($order, '4242424242424242', $paidAt ?? $this->moment('2026-08-20 10:00:00'));

        return $order->fulfillments()->sole();
    }

    /**
     * A default flow of two steps — a label step, then a plain one — the
     * shape `CompleteFlowStep`, its request, and the flow-step routes all
     * exercise.
     *
     * @return array{0: FulfillmentFlowStep, 1: FulfillmentFlowStep}
     */
    public function flowFor(Seller $seller, ?string $flowName = null, string $labelStepLabel = 'Label printed', string $packStepLabel = 'Packed'): array
    {
        $flow = FulfillmentFlow::factory()->isDefault()->create([
            'seller_id' => $seller->id,
            ...($flowName === null ? [] : ['name' => $flowName]),
        ]);
        $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create(['label' => $labelStepLabel]);
        $packStep = FulfillmentFlowStep::factory()->of($flow, 1)->create(['label' => $packStepLabel]);

        return [$labelStep, $packStep];
    }

    /**
     * The seller's store, minted the way the Store screen's first visit
     * mints it — the fixture every store-write test needs a profile to
     * write against, without a `GET /seller/store` round trip.
     */
    public function storeFor(Seller $seller): StoreProfile
    {
        return app(StartStore::class)($seller);
    }

    /**
     * A paid order split across two sellers, one fulfillment each.
     */
    public function paidOrderWithTwoSellers(): Order
    {
        $order = $this->orderFor(
            $this->verifiedCustomer(),
            $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]),
            $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]),
        );

        return app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    }

    public function shippingAddress(): ShippingAddress
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

    public function moment(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when);
    }

    /**
     * Carries a listing through checkout, payment, and shipment for the given
     * seller. Shared by every test that needs a fulfillment already in transit.
     */
    public function shippedFulfillmentFor(
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
    public function deliveredFulfillmentFor(
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

        return app(ConfirmDelivered::class)($fulfillment->refresh(), $deliveredAt ?? $this->moment('2026-08-22 10:00:00'));
    }
}
