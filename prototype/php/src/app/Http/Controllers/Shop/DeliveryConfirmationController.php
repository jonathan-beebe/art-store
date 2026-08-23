<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

final class DeliveryConfirmationController extends ShopController
{
    /**
     * The route scopes the fulfillment to the order, so the only ownership
     * left to settle is the order's; ConfirmDelivered holds the rule about
     * whether the parcel is in a state to arrive.
     */
    public function __invoke(Order $order, Fulfillment $fulfillment, ConfirmDelivered $confirmDelivered): RedirectResponse
    {
        $this->authorizeVisitor('view', $order);

        $confirmDelivered($fulfillment, $this->now());

        return redirect()->route('shop.order', $order);
    }
}
