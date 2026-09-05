<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Favorites\FavoriteChange;
use App\Models\Customer;
use App\Models\Listing;
use DateTimeImmutable;

final readonly class ToggleFavorite
{
    public function __construct(private Analytics $analytics) {}

    public function __invoke(Customer $customer, Listing $listing, DateTimeImmutable $now): FavoriteChange
    {
        $favorite = $customer->favorites()->firstWhere('listing_id', $listing->id);

        $change = FavoriteChange::fromCurrentState($favorite !== null);

        match ($change) {
            FavoriteChange::Added => $customer->favorites()->create(['listing_id' => $listing->id]),
            FavoriteChange::Removed => $favorite?->delete(),
        };

        $this->analytics->recordEvent(AnalyticsEvent::forListing($change->listingEvent(), $listing->id, $customer->id, $now));

        return $change;
    }
}
