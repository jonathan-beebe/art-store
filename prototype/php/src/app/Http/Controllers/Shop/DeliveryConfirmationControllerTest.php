<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Fulfillment;
use Tests\StorefrontTestCase;

final class DeliveryConfirmationControllerTest extends StorefrontTestCase
{
    public function test_the_customer_confirms_delivery(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $fulfillment = $this->shippedFulfillmentFor($shopper);

        $response = $this->post(route('shop.order.delivered', [$fulfillment->order_id, $fulfillment->id]));

        $response->assertRedirect(route('shop.order', $fulfillment->order_id));
        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $fulfillment->order->fresh()->status);
    }

    public function test_another_customer_cannot_confirm_delivery(): void
    {
        $fulfillment = $this->shippedFulfillmentFor($this->verifiedCustomer());
        $this->arriveAs($this->verifiedCustomer());

        $response = $this->post(route('shop.order.delivered', [$fulfillment->order_id, $fulfillment->id]));

        $response->assertNotFound();
        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->fresh()->status);
    }

    private function shippedFulfillmentFor(Customer $customer): Fulfillment
    {
        $order = $this->orderFor($customer, $this->listing($this->seller(), ['price_cents' => 24500]));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
        $fulfillment = $order->fulfillments()->sole();
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123456789GB', $this->moment('2026-08-21 09:00:00'));

        return $fulfillment->fresh();
    }
}
