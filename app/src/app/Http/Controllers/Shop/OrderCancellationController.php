<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\CancelOrder;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

final class OrderCancellationController extends ShopController
{
    /**
     * Ownership is the only question the request settles — someone else's
     * order answers 404 — and `CancelOrder` holds the rule about whether the
     * order is still one nothing has been charged for.
     */
    public function __invoke(Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $this->authorizeVisitor('view', $order);

        $cancelOrder($order, $this->now());

        return redirect()->route('shop.order', $order)->with('status', 'Order cancelled.');
    }
}
