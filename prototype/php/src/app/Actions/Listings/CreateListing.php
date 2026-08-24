<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingSlug;
use App\Domain\Listings\ListingStatus;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\Seller;
use App\Support\Story;
use Illuminate\Http\UploadedFile;

final readonly class CreateListing
{
    public function __construct(private StoreListingImage $storeListingImage) {}

    public function __invoke(Seller $seller, ListingDraft $draft, ?UploadedFile $image = null): Listing
    {
        $story = Story::for(StoryEvent::ListingCreate)->will('creating a listing', [
            'seller_id' => $seller->id,
        ]);

        $base = ListingSlug::base($draft->title);

        $listing = $seller->listings()->create($draft->attributes() + [
            'slug' => ListingSlug::firstFree($draft->title, $this->slugsStartingWith($base)),
            'status' => ListingStatus::Draft,
            'image_path' => $image === null ? null : ($this->storeListingImage)($image),
        ]);

        $story->did('created the listing', [
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'slug' => $listing->slug,
            'price_cents' => $listing->price_cents,
            'status' => $listing->status->value,
        ]);

        return $listing;
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
