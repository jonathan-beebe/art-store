<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Auth\SendMagicLink;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Auth\ActorType;
use App\Domain\Cart\CartTotals;
use App\Domain\Orders\OrderPayment;
use App\Domain\Orders\ShippingAddress;
use App\Domain\Shop\CheckoutPurchaser;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'isVerified' => $this->isVerified($visitor),
        ]);
    }

    /**
     * The card is only asked for once the address behind the order is verified,
     * which is why a guest leaves here with a link instead of a receipt: an
     * unverified order has nowhere to hold a card number until it is.
     */
    public function place(
        Request $request,
        PlaceOrder $placeOrder,
        FinalizeOrder $finalizeOrder,
        SendMagicLink $sendMagicLink,
    ): RedirectResponse {
        $visitor = $this->visitor();
        $submitted = $this->validated($request, $this->isVerified($visitor));
        $cart = ($this->currentCart)($visitor);

        if ($cart->items()->doesntExist()) {
            return redirect()->route('shop.cart');
        }

        $purchaser = CheckoutPurchaser::forCustomer(
            $visitor->id,
            $visitor->email,
            $visitor->email_verified_at?->toDateTimeImmutable(),
            $submitted['email'],
        );

        $now = $this->now();
        $order = $placeOrder($cart, $purchaser, $this->shippingAddress($submitted), $now);

        if (OrderPayment::isPayableBy($order->status, $purchaser->isEmailVerified())) {
            $finalizeOrder($order, $submitted['card_number'], $now);

            return redirect()->route('shop.order', $order);
        }

        $sendMagicLink(
            $purchaser->email,
            ActorType::Customer,
            route('shop.order.pay', $order, absolute: false),
        );

        return redirect()->route('shop.order', $order);
    }

    private function isVerified(Customer $visitor): bool
    {
        return $visitor->email_verified_at !== null;
    }

    /**
     * @return array<string, string>
     */
    private function validated(Request $request, bool $isVerified): array
    {
        return $request->validate([
            'email' => ['required', 'email'],
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_line1' => ['required', 'string', 'max:255'],
            'shipping_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:255'],
            'shipping_region' => ['required', 'string', 'max:255'],
            'shipping_postal_code' => ['required', 'string', 'max:32'],
            'shipping_country' => ['required', 'string', 'max:64'],
            'card_number' => [$isVerified ? 'required' : 'nullable', 'string', 'max:32'],
        ]);
    }

    /**
     * @param  array<string, string>  $submitted
     */
    private function shippingAddress(array $submitted): ShippingAddress
    {
        return new ShippingAddress(
            $submitted['shipping_name'],
            $submitted['shipping_line1'],
            $submitted['shipping_line2'] ?? null,
            $submitted['shipping_city'],
            $submitted['shipping_region'],
            $submitted['shipping_postal_code'],
            $submitted['shipping_country'],
        );
    }
}
