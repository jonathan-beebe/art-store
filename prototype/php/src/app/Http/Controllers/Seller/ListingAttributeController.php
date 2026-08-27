<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\SetListingAttributes;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ListingAttributeRequest;
use App\Models\Listing;
use App\Support\Configurator\ListingEditPageData;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class ListingAttributeController extends SellerController
{
    public function update(
        ListingAttributeRequest $request,
        Listing $listing,
        SetListingAttributes $setListingAttributes,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.edit', ListingEditPageData::for($listing));
        }

        $setListingAttributes($listing, $request->selections());

        return redirect()->route('seller.listings.edit', $listing)->with('status', 'Attributes updated.');
    }
}
