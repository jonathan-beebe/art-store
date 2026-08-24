<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Orders\CancelOrder;
use Illuminate\Support\Facades\Event;

it('is raised when an unpaid order ends', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    Event::fake([OrderCancelled::class]);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    Event::assertDispatched(
        OrderCancelled::class,
        fn (OrderCancelled $event): bool => $event->order->is($order)
            && $event->cancelledAt->format('Y-m-d H:i:s') === '2026-08-21 09:00:00',
    );
});
