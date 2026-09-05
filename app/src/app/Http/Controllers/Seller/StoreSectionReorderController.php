<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\MoveStoreSection;
use App\Http\Requests\Seller\ReorderStoreSectionRequest;
use App\Models\StoreSection;
use Illuminate\Http\RedirectResponse;

final class StoreSectionReorderController extends SellerController
{
    public function __invoke(
        ReorderStoreSectionRequest $request,
        StoreSection $section,
        MoveStoreSection $moveStoreSection,
    ): RedirectResponse {
        $moveStoreSection($section, $request->direction());

        return redirect()->route('seller.store.show')->with('status', 'Moved.');
    }
}
