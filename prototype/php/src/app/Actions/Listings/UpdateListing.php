<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Logging\StoryEvent;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Support\Story;

final readonly class UpdateListing
{
    /**
     * The slug is left alone: a renamed listing keeps the storefront URL it was
     * shared under. Images are the Images screen's own concern (DSGN-002) —
     * the Basics save this action backs never carries an upload.
     */
    public function __invoke(Listing $listing, ListingDraft $draft): Listing
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating a listing', [
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
        ], function (Story $story) use ($listing, $draft): Listing {
            $previousCategoryId = $listing->category_id;

            $listing->update($draft->attributes());

            if ($listing->category_id !== $previousCategoryId) {
                $this->pruneAttributesTheNewCategoryDoesNotGrant($listing);
            }

            $story->did('updated the listing', [
                'listing_id' => $listing->id,
                'seller_id' => $listing->seller_id,
                'price_cents' => $listing->price_cents,
            ]);

            return $listing;
        });
    }

    /**
     * A category change can leave `listing_attributes` rows the new category
     * never granted — a Metal value the listing held under Jewelry means
     * nothing once it moves to Home Goods. Dropped rather than left stale, so
     * the Highlights panel and the publish gate both read only what the
     * current category actually grants.
     */
    private function pruneAttributesTheNewCategoryDoesNotGrant(Listing $listing): void
    {
        $grantedPropertyIds = $listing->category_id === null ? [] : array_values(CategoryProperty::query()
            ->where('category_id', $listing->category_id)
            ->where('usable_as_attribute', true)
            ->pluck('property_id')
            ->all());

        $listing->listingAttributes()->whereNotIn('property_id', $grantedPropertyIds)->delete();
    }
}
