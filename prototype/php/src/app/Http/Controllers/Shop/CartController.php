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
        $cart = $this->visitor()->cart()->load('items.listing.seller', 'items.variant', 'items.unit');

        return view('shop.cart', [
            'cart' => $cart,
            'totals' => CartTotals::from($cart->lines()),
            'plan' => $cart->placementPlan(),
        ]);
    }

    public function add(AddToCartRequest $request, Listing $listing, AddToCart $addToCart): RedirectResponse
    {
        $configuration = $request->configuration();

        $addToCart(
            $this->visitor()->cart(),
            $listing,
            $request->quantity(),
            $this->now(),
            $configuration->hasVariants,
            $request->variant(),
            $configuration->selectedUnitId,
            $configuration->configurationSnapshot,
            $configuration->answersSnapshot,
            $configuration->fingerprintAnswers,
        );

        return redirect()->route('shop.cart');
    }

    public function remove(Listing $listing, RemoveFromCart $removeFromCart): RedirectResponse
    {
        $removeFromCart($this->visitor()->cart(), $listing);

        return redirect()->route('shop.cart');
    }
}
