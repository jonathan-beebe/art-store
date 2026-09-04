<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Logging\StoryEvent;
use App\Models\ListingImage;
use App\Support\Story;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes one listing image, row and file together. Removing the cover
 * leaves whichever image now holds the lowest position as the new one —
 * nothing here re-numbers the rest.
 */
final readonly class RemoveListingImage
{
    public function __invoke(ListingImage $image): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('removing a listing image', [
            'listing_id' => $image->listing_id,
            'listing_image_id' => $image->id,
        ], function (Story $story) use ($image): void {
            $listingId = $image->listing_id;
            $imageId = $image->id;
            $path = $image->path;

            $image->delete();
            Storage::disk('public')->delete($path);

            $story->did('removed a listing image', [
                'listing_id' => $listingId,
                'listing_image_id' => $imageId,
            ]);
        });
    }
}
