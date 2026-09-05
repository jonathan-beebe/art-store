<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\AddStoreSection;
use App\Actions\Store\RemoveStoreSection;
use App\Actions\Store\SaveStoreSection;
use App\Http\Requests\Seller\StoreSectionRequest;
use App\Models\StoreSection;
use Illuminate\Http\RedirectResponse;

final class StoreSectionController extends SellerController
{
    public function store(StoreSectionRequest $request, AddStoreSection $addStoreSection): RedirectResponse
    {
        $section = $addStoreSection($request->storeProfile(), $request->kind());

        return redirect()
            ->route('seller.store.show')
            ->with('status', $section->kind->label().' section added.');
    }

    public function update(StoreSectionRequest $request, StoreSection $section, SaveStoreSection $saveStoreSection): RedirectResponse
    {
        $saveStoreSection($section, $request->heading(), $request->body(), $request->imageIds());

        return redirect()->route('seller.store.show')->with('status', 'Section saved.');
    }

    public function destroy(StoreSection $section, RemoveStoreSection $removeStoreSection): RedirectResponse
    {
        $this->authorize('update', $section->storeProfile);

        $removeStoreSection($section);

        return redirect()->route('seller.store.show')->with('status', 'Section removed.');
    }
}
