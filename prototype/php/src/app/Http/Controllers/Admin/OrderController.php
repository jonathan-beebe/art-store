<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', OrderStatus::class);
        $customerId = $request->filled('customer') ? $request->string('customer')->toString() : null;

        return view('admin.orders.index', [
            'orders' => $this->orders($status, $customerId),
            'customers' => Customer::query()->orderBy('name')->orderBy('id')->get(),
            'status' => $status,
            'statuses' => OrderStatus::cases(),
            'customerId' => $customerId,
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load([
                'customer',
                'items.seller',
                'payments',
                'fulfillments.seller',
                'refunds.fulfillment.seller',
            ]),
            // DSGN-006: the show route's list pane is the same default,
            // unfiltered list the index route opens with — a show URL
            // carries no query string to filter it by.
            'cellOrders' => $this->orders(null, null),
        ]);
    }

    /**
     * @return Collection<int, Order>
     */
    private function orders(?OrderStatus $status, ?string $customerId): Collection
    {
        return Order::query()
            ->ofStatus($status)
            ->ofCustomer($customerId)
            ->with('customer')
            ->withCount('items')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->get();
    }
}
