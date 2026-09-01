<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingViewCollapse;
use App\Models\Listing;
use App\Models\ListingEvent;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Files one interaction with a listing. A `view` the core collapses per hour
 * returns null on its second write in a window rather than a row, so the
 * caller — the only one who knows whether to log it as recorded or
 * refused — can tell what happened.
 */
final readonly class RecordListingEvent
{
    public function __invoke(
        Listing $listing,
        ?string $customerId,
        ListingEventType $type,
        DateTimeImmutable $now,
    ): ?ListingEvent {
        if (ListingViewCollapse::collapsesHourly($type) && $this->alreadyRecorded($listing, $customerId, $type, $now)) {
            return null;
        }

        return ListingEvent::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'customer_id' => $customerId,
            'type' => $type,
            'occurred_at' => $now,
        ]);
    }

    private function alreadyRecorded(Listing $listing, ?string $customerId, ListingEventType $type, DateTimeImmutable $now): bool
    {
        return ListingEvent::query()
            ->where('listing_id', $listing->id)
            ->where('type', $type)
            ->where('occurred_at', '>=', ListingViewCollapse::windowStart($now))
            ->where(fn (Builder $query) => $customerId === null ? $query->whereNull('customer_id') : $query->where('customer_id', $customerId))
            ->exists();
    }
}
