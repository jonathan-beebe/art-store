<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Cart\CartQuantity;
use App\Domain\Customers\CustomerStanding;
use App\Domain\Listings\ListingEventType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use DateTimeImmutable;

final readonly class AddToCart
{
    public function __construct(private RecordListingEvent $recordListingEvent) {}

    public function __invoke(Cart $cart, Listing $listing, int $quantity, DateTimeImmutable $now): CartItem
    {
        CustomerStanding::assertCanShop($cart->loadMissing('customer')->customer->blockReason());

        $item = $cart->items()->firstOrNew(['listing_id' => $listing->id]);
        $item->quantity = CartQuantity::withinStock(($item->quantity ?? 0) + $quantity, $listing->quantity, $listing->status);
        $item->save();

        ($this->recordListingEvent)($listing, $cart->customer_id, ListingEventType::CartAdd, $now);

        return $item;
    }
}
