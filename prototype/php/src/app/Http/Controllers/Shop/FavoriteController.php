<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Favorites\ToggleFavorite;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class FavoriteController extends ShopController
{
    public function index(): View
    {
        return view('shop.favorites', [
            'listings' => $this->visitor()->favoriteListings()
                ->with('seller')
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
