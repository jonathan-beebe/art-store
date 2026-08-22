<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\OrderPayment;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OrderPaymentController extends ShopController
{
    public function show(Order $order): View|RedirectResponse
    {
        $order = $this->orderOfVisitor($order);

        if (! OrderPayment::awaitsPayment($order->status)) {
            return redirect()->route('shop.order', $order);
        }

        if (! $this->isPayable($order)) {
            return redirect()->route('auth.customer.login', [
                'redirect_to' => route('shop.order.pay', $order, absolute: false),
            ]);
        }

        return $this->page('shop.pay', [
            'order' => $order,
            'payment' => $order->payments()->orderByDesc('id')->first(),
        ]);
    }

    public function pay(Request $request, Order $order, FinalizeOrder $finalizeOrder): RedirectResponse
    {
        $order = $this->orderOfVisitor($order);
        $submitted = $request->validate(['card_number' => ['required', 'string', 'max:32']]);

        abort_unless($this->isPayable($order), 404);

        $finalizeOrder($order, $submitted['card_number'], $this->now());

        return redirect()->route('shop.order', $order);
    }

    private function isPayable(Order $order): bool
    {
        return OrderPayment::isPayableBy($order->status, $this->visitor()->email_verified_at !== null);
    }
}
