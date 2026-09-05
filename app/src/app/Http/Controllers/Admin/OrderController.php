<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Paging\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', OrderStatus::class);
        $customerId = $request->filled('customer') ? $request->string('customer')->toString() : null;
        $window = ListPaneWindow::of($this->ordersQuery($status, $customerId));

        return view('admin.orders.index', [
            'orders' => $window->items,
            'ordersTotal' => $window->total,
            'customers' => Customer::query()->orderBy('name')->orderBy('id')->get(),
            'status' => $status,
            'statuses' => OrderStatus::cases(),
            'customerId' => $customerId,
        ]);
    }

    public function show(Order $order): View
    {
        // DSGN-006: the show route's list pane is the same default,
        // unfiltered list the index route opens with — a show URL
        // carries no query string to filter it by.
        $window = ListPaneWindow::of($this->ordersQuery(null, null), $order);

        $order->load(['customer', 'items.seller', 'payments', 'fulfillments.seller', 'refunds.fulfillment.seller']);

        // isRefundable(), which the refund form below reads per fulfillment,
        // reads the order back off it — the same row already in hand, set
        // here with no second query.
        foreach ($order->fulfillments as $fulfillment) {
            $fulfillment->setRelation('order', $order);
        }

        return view('admin.orders.show', [
            'order' => $order,
            'cellOrders' => $window->items,
            'cellOrdersTotal' => $window->total,
        ]);
    }

    /**
     * @return Builder<Order>
     */
    private function ordersQuery(?OrderStatus $status, ?string $customerId): Builder
    {
        return Order::query()
            ->ofStatus($status)
            ->ofCustomer($customerId)
            ->with('customer')
            ->withCount('items')
            ->orderByDesc('placed_at')
            ->orderByDesc('id');
    }
}
