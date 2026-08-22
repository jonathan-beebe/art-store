<?php

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class UpdateListing
{
    public function __construct(private readonly StoreListingImage $storeListingImage) {}

    /**
     * The slug is left alone: a renamed listing keeps the storefront URL it was
     * shared under.
     */
    public function __invoke(Listing $listing, ListingDraft $draft, ?UploadedFile $image = null): Listing
    {
        $replaced = $listing->image_path;

        $listing->update($draft->attributes() + ($image === null
            ? []
            : ['image_path' => ($this->storeListingImage)($image)]));

        if ($image !== null && $replaced !== null) {
            Storage::disk('public')->delete($replaced);
        }

        return $listing;
    }
}
