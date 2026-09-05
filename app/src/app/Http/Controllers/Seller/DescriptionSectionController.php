<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddDescriptionSection;
use App\Actions\Configurator\DeleteDescriptionSection;
use App\Actions\Configurator\UpdateDescriptionSection;
use App\Configurator\DescriptionSectionIndexPageData;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\DescriptionSectionRequest;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class DescriptionSectionController extends SellerController
{
    public function index(Request $request, Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.description-sections.index', $this->indexData($request, $listing));
    }

    public function store(DescriptionSectionRequest $request, Listing $listing, AddDescriptionSection $add, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.description-sections.index', $this->indexData($request, $listing));
        }

        $maxPosition = $listing->descriptionSections()->max('position');
        $nextPosition = (is_numeric($maxPosition) ? (int) $maxPosition : -1) + 1;

        $add($listing, $nextPosition, $request->kind(), $request->title(), $request->bodyMd(), $request->bodyJson());

        return redirect()->route('seller.listings.description-sections.index', $listing)->with('status', 'Section added.');
    }

    public function update(
        DescriptionSectionRequest $request,
        Listing $listing,
        DescriptionSection $descriptionSection,
        UpdateDescriptionSection $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.description-sections.index', $this->indexData($request, $listing));
        }

        $update($descriptionSection, $request->kind(), $request->title(), $request->bodyMd(), $request->bodyJson());

        return redirect()->route('seller.listings.description-sections.index', $listing)->with('status', 'Section updated.');
    }

    public function destroy(
        Listing $listing,
        DescriptionSection $descriptionSection,
        DeleteDescriptionSection $delete,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.description-sections.index', DescriptionSectionIndexPageData::build($listing));
        }

        $delete($descriptionSection);

        return redirect()->route('seller.listings.description-sections.index', $listing)->with('status', 'Section removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Request $request, Listing $listing): array
    {
        $kind = $request->query('kind');

        return DescriptionSectionIndexPageData::build($listing, is_string($kind) ? $kind : null);
    }
}
