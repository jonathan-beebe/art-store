<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\RemoveFromCart;
use App\Domain\Cart\CartTotals;
use App\Http\Requests\Shop\AddToCartRequest;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function add(AddToCartRequest $request, Listing $listing, AddToCart $addToCart): RedirectResponse
    {
        $addToCart(
            ($this->currentCart)($this->visitor()),
            $listing,
            $request->quantity(),
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
