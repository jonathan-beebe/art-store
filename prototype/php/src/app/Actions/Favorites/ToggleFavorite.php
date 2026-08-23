<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Favorites\FavoriteChange;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Listing;
use DateTimeImmutable;

final class ToggleFavorite
{
    public function __construct(private readonly RecordListingEvent $recordListingEvent) {}

    public function __invoke(Customer $customer, Listing $listing, DateTimeImmutable $now): FavoriteChange
    {
        $favorite = Favorite::query()
            ->where('customer_id', $customer->id)
            ->where('listing_id', $listing->id)
            ->first();

        $change = FavoriteChange::fromCurrentState($favorite !== null);

        match ($change) {
            FavoriteChange::Added => Favorite::create([
                'customer_id' => $customer->id,
                'listing_id' => $listing->id,
            ]),
            FavoriteChange::Removed => $favorite->delete(),
        };

        ($this->recordListingEvent)($listing, $customer->id, $change->listingEvent(), $now);

        return $change;
    }
}
