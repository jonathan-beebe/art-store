<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Orders\FinalizeOrder;
use Illuminate\Support\Facades\Event;

it('is raised when a card is approved', function (): void {
    Event::fake([OrderPaid::class]);
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event): bool => $event->order->is($order)
        && $event->paidAt->format('Y-m-d H:i:s') === '2026-08-20 10:00:00');
});

it('is not raised when a card is declined', function (): void {
    Event::fake([OrderPaid::class]);
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    Event::assertNotDispatched(OrderPaid::class);
});
