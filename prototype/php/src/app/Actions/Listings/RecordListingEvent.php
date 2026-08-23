<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingEventType;
use App\Models\Listing;
use App\Models\ListingEvent;
use DateTimeImmutable;

final readonly class RecordListingEvent
{
    public function __invoke(
        Listing $listing,
        ?int $customerId,
        ListingEventType $type,
        DateTimeImmutable $now,
    ): ListingEvent {
        return ListingEvent::create([
            'listing_id' => $listing->id,
            'customer_id' => $customerId,
            'type' => $type,
            'occurred_at' => $now,
        ]);
    }
}
