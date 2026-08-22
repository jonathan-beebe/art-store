<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Listing;

final class RemoveFromCart
{
    public function __invoke(Cart $cart, Listing $listing): void
    {
        $cart->items()->where('listing_id', $listing->id)->delete();
    }
}
