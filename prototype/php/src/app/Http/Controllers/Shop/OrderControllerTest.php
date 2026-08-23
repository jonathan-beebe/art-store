<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Order;
use Tests\StorefrontTestCase;

final class OrderControllerTest extends StorefrontTestCase
{
    public function test_it_lists_the_orders_of_the_visitor(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 24500]);
        $this->orderFor($shopper, $listing);
        $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['title' => 'Winter Elm']));

        $response = $this->get('/orders');

        $response->assertOk();
        $response->assertSee('Harbour at Dawn');
        $response->assertSee('$245.00');
        $response->assertDontSee('Winter Elm');
    }

    public function test_it_groups_the_items_by_seller_with_their_fulfillment_status(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->paidOrderFor($shopper);

        $response = $this->get(route('shop.order', $order));

        $response->assertOk();
        $response->assertSee('Blue Kiln Studio');
        $response->assertSee('Harbour at Dawn');
        $response->assertSee('Awaiting shipment');
    }

    public function test_it_shows_the_carrier_and_tracking_once_shipped(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->paidOrderFor($shopper);
        $this->ship($order->fulfillments()->sole());

        $response = $this->get(route('shop.order', $order));

        $response->assertSee('Royal Mail');
        $response->assertSee('RM123456789GB');
        $response->assertSee('Confirm delivery');
    }

    public function test_it_offers_no_delivery_confirmation_before_shipping(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->paidOrderFor($shopper);

        $this->get(route('shop.order', $order))->assertDontSee('Confirm delivery');
    }

    public function test_another_customer_cannot_read_the_order(): void
    {
        $order = $this->paidOrderFor($this->verifiedCustomer());
        $this->arriveAs($this->verifiedCustomer());

        $this->get(route('shop.order', $order))->assertNotFound();
    }

    private function paidOrderFor(Customer $customer): Order
    {
        $listing = $this->listing($this->seller('Blue Kiln Studio'), [
            'title' => 'Harbour at Dawn',
            'price_cents' => 24500,
        ]);
        $order = $this->orderFor($customer, $listing);

        return app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    }

    private function ship(Fulfillment $fulfillment): void
    {
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123456789GB', $this->moment('2026-08-21 09:00:00'));
    }
}
