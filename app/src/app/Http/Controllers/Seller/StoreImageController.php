<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\AddStoreImage;
use App\Actions\Store\RemoveStoreImage;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\StoreImageRequest;
use App\Models\StoreImage;
use App\RateLimiting\RateLimitGate;
use App\Seller\Store\StorePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use RuntimeException;

final class StoreImageController extends SellerController
{
    public function store(StoreImageRequest $request, AddStoreImage $addStoreImage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $profile = $request->storeProfile();

        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build($profile)]);
        }

        $role = $request->role();

        $image = $addStoreImage($profile, $request->uploadedImage(), $role, $request->alt());

        return redirect()->route('seller.store.show')->with('status', $image === null
            ? 'The picture failed to upload; try again.'
            : $role->label().' updated.');
    }

    public function destroy(StoreImage $image, RemoveStoreImage $removeStoreImage, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $profile = $image->storeProfile ?? throw new RuntimeException('A store image belongs to a store.');

        $this->authorize('update', $profile);

        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build($profile)]);
        }

        $removeStoreImage($image);

        return redirect()->route('seller.store.show')->with('status', 'Picture removed.');
    }
}
