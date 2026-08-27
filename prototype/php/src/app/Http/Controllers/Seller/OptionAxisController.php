<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\DeleteOptionAxis;
use App\Actions\Configurator\UpdateOptionAxis;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\OptionAxisRequest;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\Property;
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

    public function store(OptionAxisRequest $request, Listing $listing, CreateOptionAxis $create, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.option-axes.index', $this->indexData($listing));
        }

        $create($listing, $request->name(), $request->property(), $request->position());

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Axis added.');
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

        $update($optionAxis, $request->name(), $request->property(), $request->position());

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Axis updated.');
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

        return redirect()->route('seller.listings.option-axes.index', $listing)->with('status', 'Axis removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'axes' => $listing->optionAxes()->with('optionValues')->orderBy('position')->get(),
            'properties' => Property::orderBy('name')->get(),
        ];
    }
}
