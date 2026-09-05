<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\SaveStore;
use App\Actions\Store\StartStore;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\Store\StoreDraft;
use App\Http\Requests\Seller\UpdateStoreRequest;
use App\RateLimiting\RateLimitGate;
use App\Seller\Store\StorePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The seller's own store: the form on the left, the buyer's view of the
 * same rows on the right. The first visit mints the store — the screen
 * needs a row to hang sections and pictures on, the same way a storefront
 * visitor's first cart page mints a cart.
 */
final class StoreController extends SellerController
{
    public function show(StartStore $startStore): View
    {
        return view('seller.store.show', ['page' => StorePageData::build($startStore($this->seller()))]);
    }

    public function update(UpdateStoreRequest $request, StartStore $startStore, SaveStore $saveStore, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $profile = $startStore($this->seller());

        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build($profile)]);
        }

        $saveStore($profile, StoreDraft::of(
            $request->name(),
            $request->slug(),
            $request->tagline(),
            $request->location(),
            $request->visibility(),
            $request->links(),
        ), $this->now());

        return redirect()->route('seller.store.show')->with('status', 'Store saved.');
    }
}
