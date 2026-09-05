<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\GenerateVariants;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\GenerateVariantsRequest;
use App\Models\Listing;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class GenerateVariantsController extends SellerController
{
    public function __invoke(GenerateVariantsRequest $request, Listing $listing, GenerateVariants $generate, RateLimitGate $rateLimit): RedirectResponse|Response
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

        $created = $generate($listing);

        return redirect()->route('seller.listings.variants.index', $listing)->with('status', count($created).' combination(s) added.');
    }
}
