<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use Illuminate\Support\Facades\Event;

it('is raised when a seller hands a fulfillment to a carrier', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();
    Event::fake([FulfillmentShipped::class]);

    app(MarkShipped::class)($fulfillment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));

    Event::assertDispatched(
        FulfillmentShipped::class,
        fn (FulfillmentShipped $event): bool => $event->fulfillment->is($fulfillment)
            && $event->shippedAt->format('Y-m-d H:i:s') === '2026-08-21 11:00:00',
    );
});
