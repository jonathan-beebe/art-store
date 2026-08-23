<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\OrderPayment;
use App\Http\Requests\Shop\PayOrderRequest;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class OrderPaymentController extends ShopController
{
    public function show(Order $order): View|RedirectResponse
    {
        $this->authorizeVisitor('view', $order);

        return $this->elsewhere($order) ?? $this->page('shop.pay', [
            'order' => $order,
            'payment' => $order->load('latestPayment')->latestPayment,
        ]);
    }

    public function pay(PayOrderRequest $request, Order $order, FinalizeOrder $finalizeOrder): RedirectResponse
    {
        if ($elsewhere = $this->elsewhere($order)) {
            return $elsewhere;
        }

        $finalizeOrder($order, $request->cardNumber(), $this->now());

        return redirect()->route('shop.order', $order);
    }

    /**
     * Where a visitor goes when this order takes no card from them right now:
     * back to the order once it is past paying, or to sign-in while the
     * address behind it is unverified. The card form and its submission
     * answer both the same way.
     */
    private function elsewhere(Order $order): ?RedirectResponse
    {
        if (! $order->status->awaitsPayment()) {
            return redirect()->route('shop.order', $order);
        }

        if (! OrderPayment::isPayableBy($order->status, $this->visitor()->isVerified())) {
            return redirect()->route('auth.customer.login', [
                'redirect_to' => route('shop.order.pay', $order, absolute: false),
            ]);
        }

        return null;
    }
}
