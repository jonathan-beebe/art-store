<?php

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingSlug;
use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;

final class CreateListing
{
    public function __construct(private readonly StoreListingImage $storeListingImage) {}

    public function __invoke(Seller $seller, ListingDraft $draft, ?UploadedFile $image = null): Listing
    {
        $base = ListingSlug::base($draft->title);

        return $seller->listings()->create($draft->attributes() + [
            'slug' => ListingSlug::firstFree($draft->title, $this->slugsStartingWith($base)),
            'status' => ListingStatus::Draft,
            'image_path' => $image === null ? null : ($this->storeListingImage)($image),
        ]);
    }

    /**
     * @return list<string>
     */
    private function slugsStartingWith(string $base): array
    {
        return Listing::query()
            ->where('slug', 'like', $base.'%')
            ->pluck('slug')
            ->all();
    }
}
