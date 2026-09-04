<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\AddStoreImage;
use App\Actions\Store\RemoveStoreImage;
use App\Http\Requests\Seller\StoreImageRequest;
use App\Models\StoreImage;
use Illuminate\Http\RedirectResponse;

final class StoreImageController extends SellerController
{
    public function store(StoreImageRequest $request, AddStoreImage $addStoreImage): RedirectResponse
    {
        $role = $request->role();

        $image = $addStoreImage($request->storeProfile(), $request->uploadedImage(), $role, $request->alt());

        return redirect()->route('seller.store.show')->with('status', $image === null
            ? 'The picture failed to upload; try again.'
            : $role->label().' updated.');
    }

    public function destroy(StoreImage $image, RemoveStoreImage $removeStoreImage): RedirectResponse
    {
        $this->authorize('update', $image->storeProfile);

        $removeStoreImage($image);

        return redirect()->route('seller.store.show')->with('status', 'Picture removed.');
    }
}
