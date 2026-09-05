<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FulfillmentShipped;
use App\Notifications\OrderShipped;
use Illuminate\Support\Facades\Notification;

it('tells the customer behind the order that it shipped', function (): void {
    $customer = $this->verifiedCustomer();
    $fulfillment = $this->shippedFulfillmentFor($this->seller(), $customer, carrier: 'USPS', trackingNumber: '9400111899');
    Notification::fake();

    app(NotifyCustomerOfShipment::class)->handle(
        new FulfillmentShipped($fulfillment, $this->moment('2026-08-21 11:00:00')),
    );

    Notification::assertSentTo(
        $customer,
        OrderShipped::class,
        fn (OrderShipped $notification): bool => $notification->toArray($customer)['body']
            === "Order {$fulfillment->order_id} shipped with USPS. Tracking number 9400111899.",
    );
});
