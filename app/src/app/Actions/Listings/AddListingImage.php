<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\Story;
use Illuminate\Http\UploadedFile;

/**
 * Puts an uploaded file on the public disk and appends it to a listing's
 * images, one place past whatever position already runs highest.
 */
final readonly class AddListingImage
{
    public function __construct(private StoreListingImage $storeListingImage) {}

    /**
     * @return ListingImage|null null when the disk write failed, leaving the
     *                           listing's images untouched
     */
    public function __invoke(Listing $listing, UploadedFile $image): ?ListingImage
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('adding a listing image', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $image): ?ListingImage {
            $path = ($this->storeListingImage)($image);

            if ($path === null) {
                $story->refused('the image upload failed', ['listing_id' => $listing->id]);

                return null;
            }

            $maxPosition = $listing->images()->max('position');
            $nextPosition = (is_numeric($maxPosition) ? (int) $maxPosition : -1) + 1;

            $listingImage = $listing->images()->create(['seller_id' => $listing->seller_id, 'path' => $path, 'position' => $nextPosition]);

            $story->did('added a listing image', [
                'listing_id' => $listing->id,
                'listing_image_id' => $listingImage->id,
                'position' => $listingImage->position,
            ]);

            return $listingImage;
        });
    }
}
