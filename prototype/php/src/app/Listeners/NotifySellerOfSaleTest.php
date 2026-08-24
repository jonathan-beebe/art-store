<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Notifications\ItemSold;
use Illuminate\Support\Facades\Notification;

it('tells every seller on the order that their item sold', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $fulfillments = $order->fulfillments()->with('seller')->get();
    Notification::fake();

    app(NotifySellerOfSale::class)->handle(new OrderPaid($order, $this->moment('2026-08-20 10:00:00')));

    Notification::assertCount(2);
    foreach ($fulfillments as $fulfillment) {
        Notification::assertSentTo($fulfillment->seller, ItemSold::class);
    }
});

it('tells each seller their own share of the order', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $fulfillment = $order->fulfillments()->with('seller')->orderBy('id')->firstOrFail();
    Notification::fake();

    app(NotifySellerOfSale::class)->handle(new OrderPaid($order, $this->moment('2026-08-20 10:00:00')));

    Notification::assertSentTo(
        $fulfillment->seller,
        ItemSold::class,
        fn (ItemSold $notification): bool => $notification->toArray($fulfillment->seller)['body']
            === "Order {$order->id} is paid. {$fulfillment->net()->format()} is held until the customer confirms delivery.",
    );
});
