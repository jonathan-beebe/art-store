<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\DeleteOptionValue;
use App\Actions\Configurator\UpdateOptionValue;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\OptionValueRequest;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Support\Configurator\AxisPropertyOptions;
use App\Support\Configurator\ListingConfiguratorSummaries;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class OptionValueController extends SellerController
{
    public function store(
        OptionValueRequest $request,
        Listing $listing,
        OptionAxis $optionAxis,
        AddOptionValue $add,
        UpdateOptionValue $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $value = $add($optionAxis, $request->label(), $request->surchargeCents(), $request->isDefault(), $request->position(), $request->propertyValue(), $request->priceCents());

        if ($request->isDefault()) {
            $this->unsetOtherDefaults($optionAxis, $value, $update);
        }

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Option added.');
    }

    public function update(
        OptionValueRequest $request,
        Listing $listing,
        OptionAxis $optionAxis,
        OptionValue $optionValue,
        UpdateOptionValue $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $value = $update($optionValue, $request->label(), $request->surchargeCents(), $request->isDefault(), $request->position(), $request->propertyValue(), $request->priceCents());

        if ($request->isDefault()) {
            $this->unsetOtherDefaults($optionAxis, $value, $update);
        }

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Option updated.');
    }

    public function destroy(
        Listing $listing,
        OptionAxis $optionAxis,
        OptionValue $optionValue,
        DeleteOptionValue $delete,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $delete($optionValue);

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Option removed.');
    }

    /**
     * A choice offers one preselected option at a time. Each option is its
     * own form, so making one the default cannot see its siblings in the
     * same request; this clears the flag from every other option on the
     * axis through the same frozen Action that set it.
     */
    private function unsetOtherDefaults(OptionAxis $optionAxis, OptionValue $keep, UpdateOptionValue $update): void
    {
        $siblings = $optionAxis->optionValues()
            ->where('is_default', true)
            ->where('id', '!=', $keep->id)
            ->get();

        foreach ($siblings as $sibling) {
            $update($sibling, $sibling->label, $sibling->surcharge_cents, false, $sibling->position, $sibling->propertyValue, $sibling->price_cents);
        }
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
