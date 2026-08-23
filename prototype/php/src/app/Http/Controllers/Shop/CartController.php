<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\RemoveFromCart;
use App\Domain\Cart\CartTotals;
use App\Domain\Listings\ListingAvailability;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CartController extends ShopController
{
    public function show(): View
    {
        $cart = ($this->currentCart)($this->visitor())->load('items.listing.seller');

        return $this->page('shop.cart', [
            'cart' => $cart,
            'totals' => CartTotals::from($cart->lines()),
        ]);
    }

    public function add(Request $request, Listing $listing, AddToCart $addToCart): RedirectResponse
    {
        if (! ListingAvailability::isPurchasable($listing->status, $listing->quantity)) {
            return back()->with('error', 'That listing is no longer for sale.');
        }

        $submitted = $request->validate(['quantity' => ['nullable', 'integer', 'min:1']]);

        $addToCart(
            ($this->currentCart)($this->visitor()),
            $listing,
            (int) ($submitted['quantity'] ?? 1),
            $this->now(),
        );

        return redirect()->route('shop.cart');
    }

    public function remove(Listing $listing, RemoveFromCart $removeFromCart): RedirectResponse
    {
        $removeFromCart(($this->currentCart)($this->visitor()), $listing);

        return redirect()->route('shop.cart');
    }
}
