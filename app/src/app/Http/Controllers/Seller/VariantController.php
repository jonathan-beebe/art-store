<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\DeleteVariant;
use App\Actions\Configurator\UpdateVariant;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\CreateVariantRequest;
use App\Http\Requests\Seller\UpdateVariantRequest;
use App\Models\Listing;
use App\Models\Variant;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class VariantController extends SellerController
{
    public function index(Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.variants.index', $this->indexData($listing));
    }

    public function store(CreateVariantRequest $request, Listing $listing, CreateVariant $create, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.index', $this->indexData($listing));
        }

        $optionValues = $request->optionValues();
        $variant = $create($listing, $optionValues, $request->priceOverrideCents(), $request->quantity(), $request->isSerialized(), true, $request->sku());

        // The no-choices "Start listing pieces" entry has no option values
        // and is marked one-of-a-kind. It lands the seller on that
        // combination's own pieces screen; an axis-free grid has nothing
        // else to show.
        if ($optionValues === [] && $variant->is_serialized) {
            return redirect()->route('seller.listings.variants.units.index', [$listing, $variant])
                ->with('status', 'Piece tracking started — add your first piece below.');
        }

        return redirect()->route('seller.listings.variants.index', $listing)->with('status', 'Combination added.');
    }

    public function update(
        UpdateVariantRequest $request,
        Listing $listing,
        Variant $variant,
        UpdateVariant $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.index', $this->indexData($listing));
        }

        $update($variant, $request->priceOverrideCents(), $request->quantity(), $request->isSerialized(), $request->enabled(), $request->sku());

        return redirect()->route('seller.listings.variants.index', $listing)->with('status', 'Combination updated.');
    }

    public function destroy(Listing $listing, Variant $variant, DeleteVariant $delete, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.index', $this->indexData($listing));
        }

        $delete($variant);

        return redirect()->route('seller.listings.variants.index', $listing)->with('status', 'Combination removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
            'variants' => $listing->variants()->with('options.optionValue.axis')->orderBy('combo_key')->get(),
            'everyCombinationExists' => $listing->everyVariantCombinationExists(),
        ];
    }
}
