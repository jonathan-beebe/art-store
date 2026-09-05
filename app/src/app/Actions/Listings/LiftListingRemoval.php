<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingRemovalKind;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\ListingRemoval;
use DateTimeImmutable;

final readonly class LiftListingRemoval
{
    public function __invoke(Listing $listing, DateTimeImmutable $now): ListingRemoval
    {
        return Story::for(StoryEvent::ModerationLiftListingRemoval)->tell('lifting a listing removal', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $now): ListingRemoval {
            $removal = $listing->currentRemoval()
                ?? throw new DomainRuleViolation('This listing has no active removal.');

            if ($removal->kind === ListingRemovalKind::Permanent) {
                throw new DomainRuleViolation('A permanent removal cannot be lifted.');
            }

            $removal->lift($now);

            $story->did('lifted the listing removal', [
                'listing_id' => $listing->id,
                'listing_removal_id' => $removal->id,
            ]);

            return $removal;
        });
    }
}
