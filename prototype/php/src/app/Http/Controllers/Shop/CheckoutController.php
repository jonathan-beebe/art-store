<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Auth\SendMagicLink;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Auth\ActorType;
use App\Domain\Cart\CartTotals;
use App\Domain\DomainRuleViolation;
use App\Domain\Orders\BlockedLine;
use App\Domain\Orders\OrderPayment;
use App\Domain\Orders\OrderPlacementRefused;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class CheckoutController extends ShopController
{
    public function show(): View|RedirectResponse
    {
        $visitor = $this->visitor();
        $cart = $visitor->currentCart()->load('items.listing.seller');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.cart');
        }

        return view('shop.checkout', $this->viewData($cart, $visitor));
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
    ): View|RedirectResponse|Response {
        $visitor = $this->visitor();
        $cart = $visitor->currentCart();

        if ($cart->items()->doesntExist()) {
            return redirect()->route('shop.cart');
        }

        $purchaser = $request->toPurchaser($visitor);
        $now = $this->now();

        try {
            $order = $placeOrder($cart, $purchaser, $request->toShippingAddress(), $now);
        } catch (OrderPlacementRefused $refusal) {
            // Checkout is where the shopper is already looking at every
            // line: the whole cart re-renders with each blocked one named,
            // rather than a redirect that loses the form to fill in again.
            $request->flash();

            return response()->view(
                'shop.checkout',
                $this->viewData($visitor->currentCart()->load('items.listing.seller'), $visitor, $refusal->blocked),
                422,
            );
        } catch (DomainRuleViolation $violation) {
            // A refusal that is not about a specific line — the customer's
            // own standing — sends the shopper to the cart instead: it still
            // holds every line, and there is no per-line blame to show here.
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
            $now,
        );

        return redirect()->route('shop.order', $order);
    }

    /**
     * @param  list<BlockedLine>  $blocked
     * @return array<string, mixed>
     */
    private function viewData(Cart $cart, Customer $visitor, array $blocked = []): array
    {
        return [
            'cart' => $cart,
            'totals' => CartTotals::from($cart->lines()),
            'visitor' => $visitor,
            'isVerified' => $visitor->isVerified(),
            'blocked' => $blocked,
        ];
    }
}
