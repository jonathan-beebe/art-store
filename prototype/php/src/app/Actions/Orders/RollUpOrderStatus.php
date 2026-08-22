<?php

namespace App\Actions\Orders;

use App\Domain\Orders\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;

final class RollUpOrderStatus
{
    public function __invoke(Order $order): Order
    {
        $order->update([
            'status' => OrderStatus::fromFulfillments(
                $order->fulfillments()->get()->map(fn (Fulfillment $fulfillment) => $fulfillment->status)->all(),
            ),
        ]);

        return $order;
    }
}
