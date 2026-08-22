<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Orders\OrderPayment;
use App\Models\Order;
use Illuminate\Contracts\View\View;

final class OrderController extends ShopController
{
    public function index(): View
    {
        return $this->page('shop.orders', [
            'orders' => Order::query()
                ->where('customer_id', $this->visitor()->id)
                ->with('items')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function show(Order $order): View
    {
        $order = $this->orderOfVisitor($order)->load(['items.seller', 'fulfillments.seller']);
        $isVerified = $this->visitor()->email_verified_at !== null;

        return $this->page('shop.order', [
            'order' => $order,
            'fulfillments' => $order->fulfillments,
            'itemsBySeller' => $order->items->groupBy('seller_id'),
            'payment' => $order->payments()->orderByDesc('id')->first(),
            'awaitsPayment' => OrderPayment::awaitsPayment($order->status),
            'isPayable' => OrderPayment::isPayableBy($order->status, $isVerified),
        ]);
    }
}
