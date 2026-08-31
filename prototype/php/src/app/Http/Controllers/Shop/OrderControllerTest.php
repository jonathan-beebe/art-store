<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Variant;

$paidOrderFor = function (Customer $customer): Order {
    $listing = test()->listing(test()->seller('Blue Kiln Studio'), [
        'title' => 'Harbour at Dawn',
        'price_cents' => 24500,
    ]);
    $order = test()->orderFor($customer, $listing);

    return app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));
};

$ship = function (Fulfillment $fulfillment): void {
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123456789GB', test()->moment('2026-08-21 09:00:00'));
};

it('lists the orders of the visitor', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->orderFor($shopper, $listing);
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['title' => 'Winter Elm']));

    $response = $this->get('/orders');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('$245.00');
    $response->assertDontSee('Winter Elm');
});

it('paginates the order history at twenty, newest first', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $seller = $this->seller();
    for ($index = 1; $index <= 21; $index++) {
        $this->orderFor($shopper, $this->listing($seller));
    }

    $first = $this->get('/orders');
    $second = $this->get('/orders?page=2');

    $first->assertOk();
    $second->assertOk();
    expect(substr_count((string) $first->getContent(), '<li class="flex flex-wrap'))->toBe(20);
    expect(substr_count((string) $second->getContent(), '<li class="flex flex-wrap'))->toBe(1);
});

it('groups the items by seller with their fulfillment status', function () use ($paidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);

    $response = $this->get(route('shop.order', $order));

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Awaiting shipment');
});

it('shows the carrier and tracking once shipped', function () use ($paidOrderFor, $ship): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);
    $ship($order->fulfillments()->sole());

    $response = $this->get(route('shop.order', $order));

    $response->assertSee('Royal Mail');
    $response->assertSee('RM123456789GB');
    $response->assertSee('Confirm delivery');
});

it('offers no delivery confirmation before shipping', function () use ($paidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);

    $this->get(route('shop.order', $order))->assertDontSee('Confirm delivery');
});

it('offers no delivery confirmation once delivered', function () use ($paidOrderFor, $ship): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);
    $ship($order->fulfillments()->sole());
    app(ConfirmDelivered::class)($order->fulfillments()->sole(), $this->moment('2026-08-22 09:00:00'));

    $this->get(route('shop.order', $order))->assertDontSee('Confirm delivery');
});

it('refuses another customer reading the order', function () use ($paidOrderFor): void {
    $order = $paidOrderFor($this->verifiedCustomer());
    $this->arriveAs($this->verifiedCustomer());

    $this->get(route('shop.order', $order))->assertNotFound();
});

it('offers a form to message the seller for each fulfillment', function () use ($paidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);
    $fulfillment = $order->fulfillments()->sole();

    $response = $this->get(route('shop.order', $order));

    $response->assertSee('Message the seller');
    $response->assertSee(route('shop.order.messages', [$order, $fulfillment]), escape: false);
});

it('renders a configured lines configuration and itemized breakdown', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $listing = $this->listing($this->seller('Blue Kiln Studio'), ['title' => 'Engraved Signet Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);
    $variant = Variant::whereHas('options', fn ($query) => $query->where('option_value_id', $roseGold->id))->sole();

    $cart = $this->cartFor($shopper);
    app(AddToCart::class)(
        $cart,
        $listing,
        1,
        $this->moment('2026-08-20 08:00:00'),
        listingHasVariants: true,
        variant: $variant,
        configuration: [['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold']],
    );
    $order = app(PlaceOrder::class)($cart, $this->purchaser($shopper), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->get(route('shop.order', $order));

    $response->assertOk();
    $response->assertSee('Metal:');
    $response->assertSee('Rose Gold');
    $response->assertSee('Base price');
    $response->assertSee('$128.00');
});

it('answers not found for a value that is not an order id, the same as an unknown one', function (string $id): void {
    $this->arriveAs($this->verifiedCustomer());

    $this->get("/orders/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'an order that does not exist' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

it('offers to cancel an order nothing has been charged for', function (): void {
    $customer = $this->visitor();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $response = $this->get("/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Cancel this order');
});

it('stops offering to cancel once the card cleared', function () use ($paidOrderFor): void {
    $order = $paidOrderFor($this->visitor());

    $response = $this->get("/orders/{$order->id}");

    $response->assertOk();
    $response->assertDontSee('Cancel this order');
});

it('shows a declined fulfillment with its reason and the amount refunded', function () use ($paidOrderFor): void {
    $order = $paidOrderFor($this->visitor());
    app(DeclineFulfillment::class)($order->fulfillments()->sole(), 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $response = $this->get("/orders/{$order->id}");

    $response->assertOk();
    $response->assertSee('Declined');
    $response->assertSee('The kiln cracked the glaze.');
    $response->assertSee('$245.00 refunded');
});
