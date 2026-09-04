<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Auth\SendMagicLink;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Auth\ActorType;
use App\Domain\Cart\CartTotals;
use App\Domain\DomainRuleViolation;
use App\Domain\Orders\BlockedLine;
use App\Domain\Orders\OrderPayment;
use App\Domain\Orders\OrderPlacementRefused;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Cart;
use App\Models\Customer;
use App\Support\RateLimiting\EmailRateLimitKey;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class CheckoutController extends ShopController
{
    public function show(Analytics $analytics): View|RedirectResponse
    {
        $visitor = $this->visitor();
        $cart = $visitor->cart()->load('items.listing.seller');

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.cart');
        }

        /** @var list<string> $listingIds */
        $listingIds = $cart->items->pluck('listing_id')->unique()->values()->all();

        $analytics->recordEvent(AnalyticsEvent::forCart(
            AnalyticsEventName::CheckoutOpen,
            $cart->id,
            $cart->customer_id,
            $this->now(),
            ['listing_ids' => $listingIds],
        ));

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
        RateLimitGate $rateLimit,
    ): View|RedirectResponse|Response {
        $visitor = $this->visitor();
        $cart = $visitor->cart();

        if ($cart->items()->doesntExist()) {
            return redirect()->route('shop.cart');
        }

        $purchaser = $request->toPurchaser($visitor);
        $now = $this->now();

        try {
            $rateLimit->check(RateLimitName::Checkout, (string) $visitor->id);

            // An unverified guest leaves this method with a magic link
            // rather than a receipt (below), so that budget is spent here
            // too — ahead of placing the order, the same as the checkout
            // budget just above, so a trip on either leaves no order behind.
            if (! $purchaser->isEmailVerified()) {
                $rateLimit->checkEach(RateLimitName::MagicLinkRequest, [
                    EmailRateLimitKey::for($request->email()),
                    'ip:'.$request->ip(),
                ]);
            }

            $order = $placeOrder($cart, $purchaser, $request->toShippingAddress(), $now);
        } catch (RateLimitExceeded $exceeded) {
            $request->flash();

            return $this->tooManyRequests(
                $exceeded,
                'shop.checkout',
                $this->viewData($visitor->cart()->load('items.listing.seller'), $visitor),
            );
        } catch (OrderPlacementRefused $refusal) {
            // Checkout is where the shopper is already looking at every
            // line: the whole cart re-renders with each blocked one named,
            // rather than a redirect that loses the form to fill in again.
            // `flash()` leaves the card fields behind (`ShopRequest`).
            $request->flash();

            return response()->view(
                'shop.checkout',
                $this->viewData($visitor->cart()->load('items.listing.seller'), $visitor, $refusal->blocked),
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
