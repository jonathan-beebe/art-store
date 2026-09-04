<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\SaveStore;
use App\Actions\Store\StartStore;
use App\Domain\Store\StoreDraft;
use App\Http\Requests\Seller\UpdateStoreRequest;
use App\Seller\Store\StorePageData;
use Illuminate\Http\RedirectResponse;
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
        return view('seller.store.show', (array) StorePageData::build($startStore($this->seller())));
    }

    public function update(UpdateStoreRequest $request, StartStore $startStore, SaveStore $saveStore): RedirectResponse
    {
        $profile = $startStore($this->seller());

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
