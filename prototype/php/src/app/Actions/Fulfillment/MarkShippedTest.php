<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use DomainException;
use Tests\CommerceTestCase;

final class MarkShippedTest extends CommerceTestCase
{
    public function test_it_records_the_carrier_and_the_tracking_number(): void
    {
        $order = $this->paidOrder($this->verifiedCustomer());
        $fulfillment = $order->fulfillments()->sole();

        $fulfillment = app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        $this->assertSame(FulfillmentStatus::Shipped, $fulfillment->status);
        $this->assertSame('USPS', $fulfillment->carrier);
        $this->assertSame('9400111899', $fulfillment->tracking_number);
        $this->assertSame('2026-08-21 11:00:00', $fulfillment->shipped_at->format('Y-m-d H:i:s'));
    }

    public function test_the_only_fulfillment_shipping_ships_the_order(): void
    {
        $order = $this->paidOrder($this->verifiedCustomer());

        app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }

    public function test_one_of_two_fulfillments_shipping_partially_ships_the_order(): void
    {
        $order = $this->paidOrder(
            $this->verifiedCustomer(),
            $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]),
            $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]),
        );

        app(MarkShipped::class)($order->fulfillments()->orderBy('id')->first(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        $this->assertSame(OrderStatus::PartiallyShipped, $order->refresh()->status);
    }

    public function test_it_tells_the_customer_the_order_shipped(): void
    {
        $customer = $this->verifiedCustomer();
        $order = $this->paidOrder($customer);

        app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        $notification = Notification::query()->where('customer_id', $customer->id)->sole();
        $this->assertSame('Order shipped', $notification->subject);
        $this->assertStringContainsString('USPS', $notification->body);
        $this->assertStringContainsString('9400111899', $notification->body);
    }

    public function test_it_refuses_to_ship_a_fulfillment_twice(): void
    {
        $order = $this->paidOrder($this->verifiedCustomer());
        $markShipped = app(MarkShipped::class);
        $fulfillment = $markShipped($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        $this->expectException(DomainException::class);

        $markShipped($fulfillment, 'FedEx', '7712349', $this->moment('2026-08-21 12:00:00'));
    }

    private function paidOrder(Customer $customer, ...$listings): Order
    {
        $listings = $listings ?: [$this->listing($this->seller(), ['price_cents' => 45000])];

        return app(FinalizeOrder::class)(
            $this->orderFor($customer, ...$listings),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );
    }
}
