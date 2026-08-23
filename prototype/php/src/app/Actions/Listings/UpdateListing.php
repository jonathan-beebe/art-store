<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Models\Listing;
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
        $replaced = $listing->image_path;
        $storedImage = $image === null ? null : ($this->storeListingImage)($image);

        $listing->update($draft->attributes() + ($storedImage === null
            ? []
            : ['image_path' => $storedImage]));

        if ($storedImage !== null && $replaced !== null) {
            Storage::disk('public')->delete($replaced);
        }

        return $listing;
    }
}
