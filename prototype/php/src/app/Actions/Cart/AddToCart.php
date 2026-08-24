<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Cart\CartQuantity;
use App\Domain\Customers\CustomerStanding;
use App\Domain\Listings\ListingEventType;
use App\Logging\StoryEvent;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use App\Support\Story;
use DateTimeImmutable;

final readonly class AddToCart
{
    public function __construct(private RecordListingEvent $recordListingEvent) {}

    public function __invoke(Cart $cart, Listing $listing, int $quantity, DateTimeImmutable $now): CartItem
    {
        $item = $cart->items()->firstOrNew(['listing_id' => $listing->id]);
        $held = $item->quantity ?? 0;

        // A cart holds one line per listing, so the second time a shopper adds
        // the same listing the line is raised rather than added.
        $raises = $item->exists;

        return Story::for($raises ? StoryEvent::CartUpdate : StoryEvent::CartAdd)->tell(
            $raises ? 'raising a cart line' : 'adding a listing to the cart',
            [
                'cart_id' => $cart->id,
                'listing_id' => $listing->id,
                'quantity' => $held + $quantity,
            ],
            function (Story $story) use ($cart, $listing, $item, $held, $quantity, $raises, $now): CartItem {
                CustomerStanding::assertCanShop($cart->loadMissing('customer')->customer->blockReason());

                $item->quantity = CartQuantity::withinStock($held + $quantity, $listing->quantity, $listing->status);
                $item->save();

                ($this->recordListingEvent)($listing, $cart->customer_id, ListingEventType::CartAdd, $now);

                $story->did($raises ? 'raised the cart line' : 'added the listing to the cart', [
                    'cart_id' => $cart->id,
                    'cart_item_id' => $item->id,
                    'listing_id' => $listing->id,
                    'quantity' => $item->quantity,
                ]);

                return $item;
            },
        );
    }
}
