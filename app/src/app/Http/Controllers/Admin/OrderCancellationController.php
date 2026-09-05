<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

final class OrderCancellationController extends Controller
{
    public function __invoke(Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $cancelOrder($order, $this->now());

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order cancelled.');
    }
}
