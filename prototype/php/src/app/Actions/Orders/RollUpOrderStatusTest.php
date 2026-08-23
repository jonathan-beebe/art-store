<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Order;
use Tests\CommerceTestCase;

final class RollUpOrderStatusTest extends CommerceTestCase
{
    public function test_a_paid_order_with_one_fulfillment_shipped_of_two_is_partially_shipped(): void
    {
        $order = $this->paidOrderWithTwoSellers();
        $order->fulfillments()->orderBy('id')->first()->update(['status' => FulfillmentStatus::Shipped]);

        $order = app(RollUpOrderStatus::class)($order);

        $this->assertSame(OrderStatus::PartiallyShipped, $order->status);
    }

    public function test_an_order_with_every_fulfillment_shipped_is_shipped(): void
    {
        $order = $this->paidOrderWithTwoSellers();
        $order->fulfillments()->update(['status' => FulfillmentStatus::Shipped]);

        $order = app(RollUpOrderStatus::class)($order);

        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    public function test_an_order_with_every_fulfillment_delivered_is_delivered(): void
    {
        $order = $this->paidOrderWithTwoSellers();
        $order->fulfillments()->update(['status' => FulfillmentStatus::Shipped]);
        app(RollUpOrderStatus::class)($order);
        $order->fulfillments()->update(['status' => FulfillmentStatus::Delivered]);

        $order = app(RollUpOrderStatus::class)($order);

        $this->assertSame(OrderStatus::Delivered, $order->status);
    }

    private function paidOrderWithTwoSellers(): Order
    {
        $order = $this->orderFor(
            $this->verifiedCustomer(),
            $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]),
            $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]),
        );

        return app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    }
}
