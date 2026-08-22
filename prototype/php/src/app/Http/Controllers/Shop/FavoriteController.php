<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Favorites\ToggleFavorite;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class FavoriteController extends ShopController
{
    public function index(): View
    {
        return $this->page('shop.favorites', [
            'listings' => Listing::query()
                ->whereIn('id', Favorite::query()
                    ->where('customer_id', $this->visitor()->id)
                    ->select('listing_id'))
                ->with('seller')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function toggle(Listing $listing, ToggleFavorite $toggleFavorite): RedirectResponse
    {
        $toggleFavorite($this->visitor(), $listing, $this->now());

        return back();
    }
}
