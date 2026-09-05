<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\AddQuantityBreak;
use App\Actions\Configurator\DeleteQuantityBreak;
use App\Actions\Configurator\UpdateQuantityBreak;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\QuantityBreakRequest;
use App\Models\Listing;
use App\Models\QuantityBreak;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class QuantityBreakController extends SellerController
{
    public function index(Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.quantity-breaks.index', $this->indexData($listing));
    }

    public function store(QuantityBreakRequest $request, Listing $listing, AddQuantityBreak $add, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.quantity-breaks.index', $this->indexData($listing));
        }

        $add($listing, $request->minQty(), $request->discountBps());

        return redirect()->route('seller.listings.quantity-breaks.index', $listing)->with('status', 'Breakpoint added.');
    }

    public function update(
        QuantityBreakRequest $request,
        Listing $listing,
        QuantityBreak $quantityBreak,
        UpdateQuantityBreak $update,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.quantity-breaks.index', $this->indexData($listing));
        }

        $update($quantityBreak, $request->minQty(), $request->discountBps());

        return redirect()->route('seller.listings.quantity-breaks.index', $listing)->with('status', 'Breakpoint updated.');
    }

    public function destroy(Listing $listing, QuantityBreak $quantityBreak, DeleteQuantityBreak $delete, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $this->authorize('update', $listing);

        try {
            $rateLimit->check(RateLimitName::ListingWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.listings.quantity-breaks.index', $this->indexData($listing));
        }

        $delete($quantityBreak);

        return redirect()->route('seller.listings.quantity-breaks.index', $listing)->with('status', 'Breakpoint removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'quantityBreaks' => $listing->quantityBreaks()->orderBy('min_qty')->get(),
        ];
    }
}
