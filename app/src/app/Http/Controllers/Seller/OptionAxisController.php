<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\DeleteOptionAxis;
use App\Actions\Configurator\UpdateOptionAxis;
use App\Domain\Configurator\PricingMode;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\OptionAxisRequest;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Support\Configurator\AxisPropertyOptions;
use App\Support\Configurator\ListingConfiguratorSummaries;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class OptionAxisController extends SellerController
{
    public function index(Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.option-axes.index', $this->indexData($listing));
    }

    public function store(
        OptionAxisRequest $request,
        Listing $listing,
        CreateOptionAxis $create,
        AddOptionValue $addValue,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $property = $request->property();
        $axis = $create($listing, $request->name(), $property, $request->position(), $request->pricingMode());

        // Catalog choices first (doc §4): a catalog property pre-fills the
        // choice's options from the property's own values — staged as
        // ordinary, editable/removable database rows the seller can adjust
        // before the choice is put to use. A `standalone` choice skips
        // this: its options need their own price and the catalog has none
        // to offer, so the axis keeps its catalog link (still searchable)
        // while the seller adds priced options by hand.
        if ($property !== null && $axis->pricing_mode !== PricingMode::Standalone) {
            foreach ($property->values()->orderBy('position')->get() as $index => $value) {
                $addValue($axis, $value->label, 0, $index === 0, $index, $value);
            }
        }

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Choice added.');
    }

    public function update(
        OptionAxisRequest $request,
        Listing $listing,
        OptionAxis $optionAxis,
        UpdateOptionAxis $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $update($optionAxis, $request->name(), $request->property(), $request->position(), $request->pricingMode());

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Choice updated.');
    }

    public function destroy(Listing $listing, OptionAxis $optionAxis, DeleteOptionAxis $delete, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $delete($optionAxis);

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Choice removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
            'properties' => AxisPropertyOptions::for($listing),
            'combinations' => ListingConfiguratorSummaries::choices($listing),
        ];
    }
}
