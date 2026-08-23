<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Orders\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;

final readonly class RollUpOrderStatus
{
    /**
     * The caller hands over an order whose `fulfillments` are loaded; the
     * roll-up reads those rows rather than asking for them again.
     */
    public function __invoke(Order $order): Order
    {
        $order->update([
            'status' => OrderStatus::fromFulfillments(
                array_values($order->fulfillments->map(fn (Fulfillment $fulfillment) => $fulfillment->status)->all()),
            ),
        ]);

        return $order;
    }
}
