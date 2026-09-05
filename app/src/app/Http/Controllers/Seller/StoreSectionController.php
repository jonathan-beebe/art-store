<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\AddStoreSection;
use App\Actions\Store\RemoveStoreSection;
use App\Actions\Store\SaveStoreSection;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\StoreSectionRequest;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\RateLimiting\RateLimitGate;
use App\Seller\Store\StorePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use RuntimeException;

final class StoreSectionController extends SellerController
{
    public function store(StoreSectionRequest $request, AddStoreSection $addStoreSection, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $profile = $request->storeProfile();

        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build($profile)]);
        }

        $section = $addStoreSection($profile, $request->kind());

        return redirect()
            ->route('seller.store.show')
            ->with('status', $section->kind->label().' section added.');
    }

    public function update(StoreSectionRequest $request, StoreSection $section, SaveStoreSection $saveStoreSection, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build(self::storeProfileOf($section))]);
        }

        $saveStoreSection($section, $request->heading(), $request->body(), $request->imageIds());

        return redirect()->route('seller.store.show')->with('status', 'Section saved.');
    }

    public function destroy(StoreSection $section, RemoveStoreSection $removeStoreSection, RateLimitGate $rateLimit): RedirectResponse|Response
    {
        $this->authorize('update', $section->storeProfile);

        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build(self::storeProfileOf($section))]);
        }

        $removeStoreSection($section);

        return redirect()->route('seller.store.show')->with('status', 'Section removed.');
    }

    private static function storeProfileOf(StoreSection $section): StoreProfile
    {
        return $section->storeProfile ?? throw new RuntimeException('A store section belongs to a store.');
    }
}
