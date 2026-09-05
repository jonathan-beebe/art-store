<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\CartItem;

final readonly class RemoveFromCart
{
    /**
     * Removes exactly the line asked for — a cart can hold more than one
     * configuration of the same listing, so a listing id alone is not
     * enough to name one line.
     */
    public function __invoke(CartItem $cartItem): void
    {
        Story::for(StoryEvent::CartRemove)->tell('removing a line from the cart', [
            'cart_id' => $cartItem->cart_id,
            'listing_id' => $cartItem->listing_id,
            'cart_item_id' => $cartItem->id,
        ], function (Story $story) use ($cartItem): void {
            $cartItem->delete();

            $story->did('removed the line from the cart', [
                'cart_id' => $cartItem->cart_id,
                'listing_id' => $cartItem->listing_id,
                'cart_item_id' => $cartItem->id,
            ]);
        });
    }
}
