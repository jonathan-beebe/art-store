<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Notifications\OrderShipped;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

$paidOrder = function (Customer $customer): Order {
    return app(FinalizeOrder::class)(
        test()->orderFor($customer, test()->listing(test()->seller(), ['price_cents' => 45000])),
        '4242 4242 4242 4242',
        test()->moment('2026-08-20 10:00:00'),
    );
};

it('records the carrier and the tracking number', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());
    $fulfillment = $order->fulfillments()->sole();

    $fulfillment = app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($fulfillment->status)->toBe(FulfillmentStatus::Shipped)
        ->and($fulfillment->carrier)->toBe('USPS')
        ->and($fulfillment->tracking_number)->toBe('9400111899')
        ->and($fulfillment->shipped_at?->format('Y-m-d H:i:s'))->toBe('2026-08-21 11:00:00');
});

it('ships the order when its only fulfillment ships', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());

    app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($order)->toHaveStatus(OrderStatus::Shipped);
});

it('partially ships the order when one of two fulfillments ships', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    app(MarkShipped::class)($order->fulfillments()->orderBy('id')->firstOrFail(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    expect($order)->toHaveStatus(OrderStatus::PartiallyShipped);
});

it('tells the customer the order shipped', function () use ($paidOrder): void {
    $customer = $this->verifiedCustomer();
    $order = $paidOrder($customer);
    Notification::fake();

    app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    Notification::assertSentTo(
        $customer,
        OrderShipped::class,
        fn (OrderShipped $notification): bool => $notification->toArray($customer)['body']
            === "Order {$order->id} shipped with USPS. Tracking number 9400111899.",
    );
});

it('tells nobody when the shipment is rolled back', function () use ($paidOrder): void {
    $customer = $this->verifiedCustomer();
    $order = $paidOrder($customer);
    $fulfillment = $order->fulfillments()->sole();
    Notification::fake();

    rescue(fn () => DB::transaction(function () use ($fulfillment): void {
        app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

        throw new RuntimeException('the carrier never took it');
    }), report: false);

    Notification::assertNothingSent();
    expect($fulfillment)->toHaveStatus(FulfillmentStatus::AwaitingShipment);
});

it('refuses to ship a fulfillment twice', function () use ($paidOrder): void {
    $order = $paidOrder($this->verifiedCustomer());
    $markShipped = app(MarkShipped::class);
    $fulfillment = $markShipped($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    $markShipped($fulfillment, 'FedEx', '7712349', $this->moment('2026-08-21 12:00:00'));
})->throws(DomainException::class);
