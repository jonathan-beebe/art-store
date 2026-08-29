<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\SetVariantsEnabledByOptionValue;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\BulkVariantsRequest;
use App\Models\Listing;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class BulkVariantsController extends SellerController
{
    public function __invoke(BulkVariantsRequest $request, Listing $listing, SetVariantsEnabledByOptionValue $setEnabled, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.index', [
                'listing' => $listing,
                'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
                'variants' => $listing->variants()->with('options.optionValue.axis')->orderBy('combo_key')->get(),
                'everyCombinationExists' => $listing->everyVariantCombinationExists(),
            ]);
        }

        $count = $setEnabled($listing, $request->optionValue(), $request->enabled());

        return redirect()->route('seller.listings.variants.index', $listing)->with('status', "{$count} combination(s) updated.");
    }
}
