<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Configurator\ConfiguratorPublishRefused;
use App\Domain\Listings\ListingStatus;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * Moves a listing along `ListingStatus::transitions()`. Putting one up for
 * sale first judges every issue publish validation holds against its
 * configurator state; `ConfiguratorPublishRefused` carries them all, and
 * the refusal ends the story with each issue in its `data`
 * (docs/spec.md §2.3). `changeStatusTo()` then refuses a transition the
 * status stopped allowing since the form was validated.
 */
final readonly class ChangeListingStatus
{
    public function __invoke(Listing $listing, ListingStatus $next): Listing
    {
        $from = $listing->status;
        $data = [
            'listing_id' => $listing->id,
            'status_from' => $from->value,
            'status_to' => $next->value,
        ];

        return Story::for(StoryEvent::ListingTransition)->tell('moving a listing to another status', $data, function (Story $story) use ($listing, $next, $data): Listing {
            return DB::transaction(function () use ($story, $listing, $next, $data): Listing {
                if ($next->isOnStorefront()) {
                    ConfiguratorPublishRefused::ifAny($listing->publishIssues());
                }

                $listing->changeStatusTo($next);

                if ($next->isOnStorefront()) {
                    Story::for(StoryEvent::ListingPublish)->did('put the listing on the storefront', [
                        'listing_id' => $listing->id,
                        'slug' => $listing->slug,
                    ]);
                }

                $story->did('moved the listing', $data);

                return $listing;
            });
        });
    }
}
