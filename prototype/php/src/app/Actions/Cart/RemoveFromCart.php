<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Logging\StoryEvent;
use App\Models\Cart;
use App\Models\Listing;
use App\Support\Story;

final readonly class RemoveFromCart
{
    public function __invoke(Cart $cart, Listing $listing): void
    {
        Story::for(StoryEvent::CartRemove)->tell('removing a listing from the cart', [
            'cart_id' => $cart->id,
            'listing_id' => $listing->id,
        ], function (Story $story) use ($cart, $listing): void {
            $removed = $cart->items()->where('listing_id', $listing->id)->delete();

            $story->did('removed the listing from the cart', [
                'cart_id' => $cart->id,
                'listing_id' => $listing->id,
                'line_count' => $removed,
            ]);
        });
    }
}
