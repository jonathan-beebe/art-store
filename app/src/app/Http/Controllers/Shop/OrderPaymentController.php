<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\BlockedLine;
use App\Domain\Orders\OrderPayment;
use App\Domain\Orders\OrderPlacementRefused;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\PayOrderRequest;
use App\Models\Order;
use App\RateLimiting\RateLimitGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class OrderPaymentController extends ShopController
{
    public function show(Order $order): View|RedirectResponse
    {
        $this->authorizeVisitor('view', $order);

        return $this->elsewhere($order) ?? view('shop.pay', $this->viewData($order));
    }

    public function pay(PayOrderRequest $request, Order $order, FinalizeOrder $finalizeOrder, RateLimitGate $rateLimit): View|RedirectResponse|Response
    {
        if ($elsewhere = $this->elsewhere($order)) {
            return $elsewhere;
        }

        try {
            $rateLimit->check(RateLimitName::PaymentAttempt, (string) $order->id);

            $finalizeOrder($order, $request->cardNumber(), $this->now());
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'shop.pay', $this->viewData($order));
        } catch (OrderPlacementRefused $refusal) {
            // A card sat declined long enough that a listing this order held
            // went stale in the meantime. The pay page names that listing
            // the same way checkout would have.
            return response()->view('shop.pay', $this->viewData($order->refresh(), $refusal->blocked), 422);
        }

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

    /**
     * @param  list<BlockedLine>  $blocked
     * @return array<string, mixed>
     */
    private function viewData(Order $order, array $blocked = []): array
    {
        return [
            'order' => $order,
            'payment' => $order->load('latestPayment')->latestPayment,
            'blocked' => $blocked,
        ];
    }
}
