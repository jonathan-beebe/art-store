<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

final class DeliveryConfirmationController extends ShopController
{
    public function __invoke(Order $order, Fulfillment $fulfillment, ConfirmDelivered $confirmDelivered): RedirectResponse
    {
        $order = $this->orderOfVisitor($order);

        abort_unless($fulfillment->order_id === $order->id, 404);
        abort_unless($fulfillment->status->canTransitionTo(FulfillmentStatus::Delivered), 404);

        $confirmDelivered($fulfillment, $this->now());

        return redirect()->route('shop.order', $order);
    }
}
