<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\AddListingImage;
use App\Actions\Listings\RemoveListingImage;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ListingImageRequest;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\Configurator\ListingImageIndexPageData;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class ListingImageController extends SellerController
{
    public function index(Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.images.index', ListingImageIndexPageData::build($listing));
    }

    public function store(ListingImageRequest $request, Listing $listing, AddListingImage $add, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.images.index', ListingImageIndexPageData::build($listing));
        }

        $image = $add($listing, $request->uploadedImage());

        return redirect()->route('seller.listings.images.index', $listing)->with('status', $image === null
            ? 'The image failed to upload; try again.'
            : 'Image added.');
    }

    public function destroy(Listing $listing, ListingImage $image, RemoveListingImage $remove, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.images.index', ListingImageIndexPageData::build($listing));
        }

        $remove($image);

        return redirect()->route('seller.listings.images.index', $listing)->with('status', 'Image removed.');
    }
}
