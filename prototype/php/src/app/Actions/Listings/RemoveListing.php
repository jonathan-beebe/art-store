<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingRemovalKind;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\ListingRemoval;
use App\Support\Story;

final readonly class RemoveListing
{
    public function __invoke(Listing $listing, ListingRemovalKind $kind, string $reason): ListingRemoval
    {
        return Story::for(StoryEvent::ModerationRemoveListing)->tell('removing a listing from the storefront', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $kind, $reason): ListingRemoval {
            if ($listing->hasActiveRemoval()) {
                throw new DomainRuleViolation('This listing already has an active removal.');
            }

            $removal = $listing->removals()->create([
                'kind' => $kind,
                'reason' => $reason,
                'lifted_at' => null,
            ]);

            $story->did('removed the listing from the storefront', [
                'listing_id' => $listing->id,
                'listing_removal_id' => $removal->id,
                'kind' => $kind->value,
                'reason' => $reason,
            ]);

            return $removal;
        });
    }
}
