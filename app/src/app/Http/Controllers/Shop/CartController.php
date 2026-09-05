<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\RemoveFromCart;
use App\Domain\Cart\CartTotals;
use App\Http\Requests\Shop\AddToCartRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

final class CartController extends ShopController
{
    /**
     * `cartOrNull()` reads without minting one: a visitor who never added
     * anything, saved or not, sees an empty cart rather than a fresh row.
     */
    public function show(): View
    {
        $cart = $this->visitor()->cartOrNull()?->load('items.listing.seller', 'items.variant.options.optionValue', 'items.unit')
            ?? (new Cart)->setRelation('items', new Collection);

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
            $this->knownVisitor()->cart(),
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

    public function remove(CartItem $cartItem, RemoveFromCart $removeFromCart): RedirectResponse
    {
        $this->authorizeVisitor('delete', $cartItem);

        $removeFromCart($cartItem);

        return redirect()->route('shop.cart');
    }
}
