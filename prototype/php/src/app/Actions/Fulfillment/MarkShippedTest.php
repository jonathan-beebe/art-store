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

beforeEach(function (): void {
    $this->paidOrder = function (Customer $customer): Order {
        return app(FinalizeOrder::class)(
            $this->orderFor($customer, $this->listing($this->seller(), ['price_cents' => 45000])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );
    };
});

it('records the carrier and the tracking number', function (): void {
    $order = ($this->paidOrder)($this->verifiedCustomer());
    $fulfillment = $order->fulfillments()->sole();

    $fulfillment = app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($fulfillment)
        ->status->toBe(FulfillmentStatus::Shipped)
        ->carrier->toBe('USPS')
        ->tracking_number->toBe('9400111899')
        ->and($fulfillment->shipped_at->format('Y-m-d H:i:s'))->toBe('2026-08-21 11:00:00');
});

it('ships the order when its only fulfillment ships', function (): void {
    $order = ($this->paidOrder)($this->verifiedCustomer());

    app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($order)->toHaveStatus(OrderStatus::Shipped);
});

it('partially ships the order when one of two fulfillments ships', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    app(MarkShipped::class)($order->fulfillments()->orderBy('id')->first(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($order)->toHaveStatus(OrderStatus::PartiallyShipped);
});

it('tells the customer the order shipped', function (): void {
    $customer = $this->verifiedCustomer();
    $order = ($this->paidOrder)($customer);

    app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    $notification = Notification::query()->where('customer_id', $customer->id)->sole();
    expect($notification->subject)->toBe('Order shipped')
        ->and($notification->body)->toContain('USPS')
        ->and($notification->body)->toContain('9400111899');
});

it('refuses to ship a fulfillment twice', function (): void {
    $order = ($this->paidOrder)($this->verifiedCustomer());
    $markShipped = app(MarkShipped::class);
    $fulfillment = $markShipped($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    $markShipped($fulfillment, 'FedEx', '7712349', $this->moment('2026-08-21 12:00:00'));
})->throws(DomainException::class);
