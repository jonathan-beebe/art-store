<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Logging\StoryEvent;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Support\Story;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateListing
{
    public function __construct(private StoreListingImage $storeListingImage) {}

    /**
     * The slug is left alone: a renamed listing keeps the storefront URL it was
     * shared under.
     */
    public function __invoke(Listing $listing, ListingDraft $draft, ?UploadedFile $image = null): Listing
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('updating a listing', [
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
        ], function (Story $story) use ($listing, $draft, $image): Listing {
            $previousCategoryId = $listing->category_id;
            $imageReplaced = $image !== null && $this->replaceCoverImage($listing, $image);

            $listing->update($draft->attributes());

            if ($listing->category_id !== $previousCategoryId) {
                $this->pruneAttributesTheNewCategoryDoesNotGrant($listing);
            }

            $story->did('updated the listing', [
                'listing_id' => $listing->id,
                'seller_id' => $listing->seller_id,
                'price_cents' => $listing->price_cents,
                'image_replaced' => $imageReplaced,
            ]);

            return $listing;
        });
    }

    /**
     * Replaces the cover — the lowest-position image — with a freshly
     * uploaded file, leaving every other image on the listing untouched. A
     * failed disk write changes nothing, so a seller keeps whatever cover
     * they had rather than losing it to a bad upload.
     */
    private function replaceCoverImage(Listing $listing, UploadedFile $image): bool
    {
        $path = ($this->storeListingImage)($image);

        if ($path === null) {
            return false;
        }

        $cover = $listing->images()->orderBy('position')->first();

        if ($cover === null) {
            $listing->images()->create(['path' => $path, 'position' => 0]);
        } else {
            Storage::disk('public')->delete($cover->path);
            $cover->update(['path' => $path]);
        }

        return true;
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
