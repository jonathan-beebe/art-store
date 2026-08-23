<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Auth\SendMagicLink;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Auth\ActorType;
use App\Domain\Cart\CartTotals;
use App\Domain\DomainRuleViolation;
use App\Domain\Orders\OrderPayment;
use App\Http\Requests\Shop\CheckoutRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class CheckoutController extends ShopController
{
    public function show(): View|RedirectResponse
    {
        $visitor = $this->visitor();
        $cart = ($this->currentCart)($visitor)->load('items.listing.seller');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.cart');
        }

        return $this->page('shop.checkout', [
            'cart' => $cart,
            'totals' => CartTotals::from($cart->lines()),
            'visitor' => $visitor,
            'isVerified' => $visitor->isVerified(),
        ]);
    }

    /**
     * The card is only asked for once the address behind the order is verified,
     * which is why a guest leaves here with a link instead of a receipt: an
     * unverified order has nowhere to hold a card number until it is.
     */
    public function place(
        CheckoutRequest $request,
        PlaceOrder $placeOrder,
        FinalizeOrder $finalizeOrder,
        SendMagicLink $sendMagicLink,
    ): RedirectResponse {
        $visitor = $this->visitor();
        $cart = ($this->currentCart)($visitor);

        if ($cart->items()->doesntExist()) {
            return redirect()->route('shop.cart');
        }

        $purchaser = $request->toPurchaser($visitor);
        $now = $this->now();

        try {
            $order = $placeOrder($cart, $purchaser, $request->toShippingAddress(), $now);
        } catch (DomainRuleViolation $violation) {
            // The cart is where the shopper can act on the refusal: it still
            // holds every line, and the one the message names is marked there.
            return redirect()->route('shop.cart')->withErrors($violation->getMessage());
        }

        if (OrderPayment::isPayableBy($order->status, $purchaser->isEmailVerified())) {
            $finalizeOrder($order, $request->cardNumber(), $now);

            return redirect()->route('shop.order', $order);
        }

        $sendMagicLink(
            $request->email(),
            ActorType::Customer,
            route('shop.order.pay', $order, absolute: false),
        );

        return redirect()->route('shop.order', $order);
    }
}
