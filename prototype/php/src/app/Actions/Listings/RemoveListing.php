<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingRemovalKind;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\ListingRemoval;
use App\Support\Story;
use Illuminate\Support\Facades\DB;

final readonly class RemoveListing
{
    public function __invoke(Listing $listing, ListingRemovalKind $kind, string $reason): ListingRemoval
    {
        return Story::for(StoryEvent::ModerationRemoveListing)->tell('removing a listing from the storefront', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $kind, $reason): ListingRemoval {
            $removal = DB::transaction(function () use ($listing, $kind, $reason): ListingRemoval {
                // Judged inside the transaction that writes, against a row
                // held for update: two admins removing the same listing at
                // once are held apart by the row they both take, so the
                // second reads the removal the first wrote and is refused.
                // The table cannot say so on its own — SQLite has no partial
                // unique index — which leaves this rule the only thing
                // holding a listing to one active removal.
                if ($listing->takeForModeration()->hasActiveRemoval()) {
                    throw new DomainRuleViolation('This listing already has an active removal.');
                }

                return $listing->removals()->create([
                    'kind' => $kind,
                    'reason' => $reason,
                    'lifted_at' => null,
                ]);
            });

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
