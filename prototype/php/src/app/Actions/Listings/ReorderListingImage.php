<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingImageMove;
use App\Logging\StoryEvent;
use App\Models\ListingImage;
use App\Support\Story;
use Illuminate\Support\Facades\DB;

/**
 * Swaps one image with its neighbor one place earlier or later — the whole
 * of "reorder" for a list with no drag-and-drop, JavaScript off. The swap
 * passes through a sentinel position rather than writing the neighbor's
 * position onto this row directly, because `listing_images` is unique on
 * `(listing_id, position)` and SQLite enforces that constraint immediately
 * rather than at commit.
 */
final readonly class ReorderListingImage
{
    private const int SENTINEL_POSITION = -1;

    public function __invoke(ListingImage $image, ListingImageMove $direction): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('reordering a listing image', [
            'listing_id' => $image->listing_id,
            'listing_image_id' => $image->id,
            'direction' => $direction->value,
        ], function (Story $story) use ($image, $direction): void {
            $neighbor = $direction === ListingImageMove::Up
                ? ListingImage::where('listing_id', $image->listing_id)->where('position', '<', $image->position)->orderByDesc('position')->first()
                : ListingImage::where('listing_id', $image->listing_id)->where('position', '>', $image->position)->orderBy('position')->first();

            if (! $neighbor instanceof ListingImage) {
                $story->did('nothing to reorder', [
                    'listing_id' => $image->listing_id,
                    'listing_image_id' => $image->id,
                ]);

                return;
            }

            DB::transaction(function () use ($image, $neighbor): void {
                $imagePosition = $image->position;
                $neighborPosition = $neighbor->position;

                $image->update(['position' => self::SENTINEL_POSITION]);
                $neighbor->update(['position' => $imagePosition]);
                $image->update(['position' => $neighborPosition]);
            });

            $story->did('reordered the listing image', [
                'listing_id' => $image->listing_id,
                'listing_image_id' => $image->id,
                'position' => $image->position,
            ]);
        });
    }
}
