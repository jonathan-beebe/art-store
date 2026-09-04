<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Favorites\ToggleFavorite;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class FavoriteController extends ShopController
{
    /**
     * The pieces the visitor saved and can still reach. A listing the seller
     * archived or an admin removed leaves this page with the rest of the
     * storefront, while the favorite row stays: the save outlives the
     * removal, so lifting one puts the card back.
     */
    public function index(): View
    {
        return view('shop.favorites', [
            'listings' => $this->visitor()->favoriteListings()
                ->onStorefront()
                ->with('seller.storeProfile')
                ->orderByDesc('listings.id')
                ->get(),
        ]);
    }

    public function toggle(Listing $listing, ToggleFavorite $toggleFavorite): RedirectResponse
    {
        $toggleFavorite($this->visitor(), $listing, $this->now());

        return back();
    }
}
