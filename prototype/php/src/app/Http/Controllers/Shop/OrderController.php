<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Orders\OrderPayment;
use App\Models\Order;
use Illuminate\Contracts\View\View;

final class OrderController extends ShopController
{
    public function index(): View
    {
        return view('shop.orders', [
            'orders' => $this->visitor()->orders()
                ->with('items')
                ->orderByDesc('placed_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorizeVisitor('view', $order);

        $order->load(['items.seller', 'fulfillments.seller', 'fulfillments.order', 'latestPayment']);
        $isVerified = $this->visitor()->isVerified();

        return view('shop.order', [
            'order' => $order,
            'fulfillments' => $order->fulfillments,
            'itemsBySeller' => $order->items->groupBy('seller_id'),
            'payment' => $order->latestPayment,
            'awaitsPayment' => $order->status->awaitsPayment(),
            'isPayable' => OrderPayment::isPayableBy($order->status, $isVerified),
        ]);
    }
}
