<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingSlug;
use App\Domain\Listings\ListingStatus;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;

final readonly class CreateListing
{
    public function __construct(private StoreListingImage $storeListingImage) {}

    public function __invoke(Seller $seller, ListingDraft $draft, ?UploadedFile $image = null): Listing
    {
        return Story::for(StoryEvent::ListingCreate)->tell('creating a listing', [
            'seller_id' => $seller->id,
        ], function (Story $story) use ($seller, $draft, $image): Listing {
            $base = ListingSlug::base($draft->title);

            $listing = $seller->listings()->create($draft->attributes() + [
                'slug' => ListingSlug::firstFree($draft->title, $this->slugsStartingWith($base)),
                'status' => ListingStatus::Draft,
            ]);

            if ($image !== null) {
                $this->storeFirstImage($listing, $image);
            }

            $story->did('created the listing', [
                'listing_id' => $listing->id,
                'seller_id' => $seller->id,
                'slug' => $listing->slug,
                'price_cents' => $listing->price_cents,
                'status' => $listing->status->value,
            ]);

            return $listing;
        });
    }

    /**
     * A silent no-op when the disk write fails — a new listing has nothing
     * to fall back to, so it is simply created imageless, the same as one
     * whose seller uploaded nothing at all.
     */
    private function storeFirstImage(Listing $listing, UploadedFile $image): void
    {
        $path = ($this->storeListingImage)($image);

        if ($path !== null) {
            $listing->images()->create(['seller_id' => $listing->seller_id, 'path' => $path, 'position' => 0]);
        }
    }

    /**
     * @return list<string>
     */
    private function slugsStartingWith(string $base): array
    {
        /** @var list<string> $slugs */
        $slugs = array_values(Listing::query()
            ->where('slug', 'like', $base.'%')
            ->pluck('slug')
            ->all());

        return $slugs;
    }
}
