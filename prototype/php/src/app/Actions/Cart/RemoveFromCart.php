<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Listing;

final readonly class RemoveFromCart
{
    public function __invoke(Cart $cart, Listing $listing): void
    {
        $cart->items()->where('listing_id', $listing->id)->delete();
    }
}
