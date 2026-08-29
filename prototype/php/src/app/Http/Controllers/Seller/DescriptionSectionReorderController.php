<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\ReorderDescriptionSection;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ReorderDescriptionSectionRequest;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\Support\Configurator\DescriptionSectionIndexPageData;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class DescriptionSectionReorderController extends SellerController
{
    public function __invoke(
        ReorderDescriptionSectionRequest $request,
        Listing $listing,
        DescriptionSection $descriptionSection,
        ReorderDescriptionSection $reorder,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.description-sections.index', DescriptionSectionIndexPageData::build($listing));
        }

        $reorder($descriptionSection, $request->direction());

        return redirect()->route('seller.listings.description-sections.index', $listing)->with('status', 'Moved.');
    }
}
