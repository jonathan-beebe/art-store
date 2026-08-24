<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Notifications\PurchaseCancelled;
use App\Notifications\SaleCancelled;
use Illuminate\Support\Facades\Notification;

it('tells the customer their order was cancelled', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));
    Notification::fake();

    app(NotifyOfCancellation::class)->handle(new OrderCancelled($order, $this->moment('2026-08-21 09:00:00')));

    Notification::assertSentTo(
        $customer,
        PurchaseCancelled::class,
        fn (PurchaseCancelled $notification): bool => $notification->toArray($customer)['body']
            === "Order {$order->id} was cancelled before it was paid. Nothing has been charged.",
    );
});

it('tells every seller on the order their pieces are back', function (): void {
    $first = $this->seller('Blue Kiln Studio');
    $second = $this->seller('Rye Press');
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($first), $this->listing($second));
    Notification::fake();

    app(NotifyOfCancellation::class)->handle(new OrderCancelled($order, $this->moment('2026-08-21 09:00:00')));

    Notification::assertSentTo([$first, $second], SaleCancelled::class);
});
