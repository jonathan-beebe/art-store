<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\UpdateUnit;
use App\Domain\Configurator\UnitLabelOrder;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\CreateUnitRequest;
use App\Http\Requests\Seller\UpdateUnitRequest;
use App\Models\Listing;
use App\Models\Unit;
use App\Models\Variant;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class UnitController extends SellerController
{
    public function index(Listing $listing, Variant $variant): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.variants.units.index', $this->indexData($listing, $variant));
    }

    public function store(CreateUnitRequest $request, Listing $listing, Variant $variant, AddUnit $add, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.units.index', $this->indexData($listing, $variant));
        }

        $add($variant, $request->label(), $request->conditionNote(), $request->specs(), $request->priceOverrideCents());

        return redirect()->route('seller.listings.variants.units.index', [$listing, $variant])->with('status', 'Unit added.');
    }

    public function update(
        UpdateUnitRequest $request,
        Listing $listing,
        Variant $variant,
        Unit $unit,
        UpdateUnit $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.variants.units.index', $this->indexData($listing, $variant));
        }

        $update($unit, $request->label(), $request->state(), $request->conditionNote(), $request->specs(), $request->priceOverrideCents());

        return redirect()->route('seller.listings.variants.units.index', [$listing, $variant])->with('status', 'Unit updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing, Variant $variant): array
    {
        return [
            'listing' => $listing,
            'variant' => $variant,
            'units' => $variant->units()->get()->sort(fn (Unit $a, Unit $b): int => UnitLabelOrder::compare($a->label, $b->label))->values(),
        ];
    }
}
