<?php

declare(strict_types=1);

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
        $this->authorizeVisitor('view', $order);

        return $this->elsewhere($order) ?? $this->page('shop.pay', [
            'order' => $order,
            'payment' => $order->payments()->orderByDesc('id')->first(),
        ]);
    }

    public function pay(Request $request, Order $order, FinalizeOrder $finalizeOrder): RedirectResponse
    {
        $this->authorizeVisitor('pay', $order);

        if ($elsewhere = $this->elsewhere($order)) {
            return $elsewhere;
        }

        $request->validate(['card_number' => ['required', 'string', 'max:32']]);

        $finalizeOrder($order, $request->string('card_number')->toString(), $this->now());

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
        if (! OrderPayment::awaitsPayment($order->status)) {
            return redirect()->route('shop.order', $order);
        }

        if (! OrderPayment::isPayableBy($order->status, $this->visitor()->email_verified_at !== null)) {
            return redirect()->route('auth.customer.login', [
                'redirect_to' => route('shop.order.pay', $order, absolute: false),
            ]);
        }

        return null;
    }
}
