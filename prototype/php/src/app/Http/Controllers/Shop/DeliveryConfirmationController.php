<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

final class DeliveryConfirmationController extends ShopController
{
    public function __invoke(Order $order, Fulfillment $fulfillment, ConfirmDelivered $confirmDelivered): RedirectResponse
    {
        $order = $this->orderOfVisitor($order);

        abort_unless($fulfillment->order_id === $order->id, 404);

        $confirmDelivered($fulfillment, $this->now());

        return redirect()->route('shop.order', $order);
    }
}
