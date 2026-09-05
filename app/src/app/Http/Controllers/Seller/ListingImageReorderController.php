<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\ReorderListingImage;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ReorderListingImageRequest;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\Configurator\ListingImageIndexPageData;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ListingImageReorderController extends SellerController
{
    public function __invoke(
        ReorderListingImageRequest $request,
        Listing $listing,
        ListingImage $image,
        ReorderListingImage $reorder,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.images.index', ListingImageIndexPageData::build($listing));
        }

        $reorder($image, $request->direction());

        return redirect()->route('seller.listings.images.index', $listing)->with('status', 'Moved.');
    }
}
