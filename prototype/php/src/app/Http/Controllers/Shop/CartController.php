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
        $cart = $this->visitor()->currentCart()->load('items.listing.seller');

        return view('shop.cart', [
            'cart' => $cart,
            'totals' => CartTotals::from($cart->lines()),
            'plan' => $cart->placementPlan(),
        ]);
    }

    public function add(AddToCartRequest $request, Listing $listing, AddToCart $addToCart): RedirectResponse
    {
        $addToCart(
            $this->visitor()->currentCart(),
            $listing,
            $request->quantity(),
            $this->now(),
        );

        return redirect()->route('shop.cart');
    }

    public function remove(Listing $listing, RemoveFromCart $removeFromCart): RedirectResponse
    {
        $removeFromCart($this->visitor()->currentCart(), $listing);

        return redirect()->route('shop.cart');
    }
}
