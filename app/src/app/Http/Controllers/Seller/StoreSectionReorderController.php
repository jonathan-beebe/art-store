<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Store\MoveStoreSection;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Seller\ReorderStoreSectionRequest;
use App\Models\StoreSection;
use App\RateLimiting\RateLimitGate;
use App\Seller\Store\StorePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use RuntimeException;

final class StoreSectionReorderController extends SellerController
{
    public function __invoke(
        ReorderStoreSectionRequest $request,
        StoreSection $section,
        MoveStoreSection $moveStoreSection,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        try {
            $rateLimit->check(RateLimitName::StoreWrite, (string) $this->seller()->id);
        } catch (RateLimitExceeded $exceeded) {
            $profile = $section->storeProfile ?? throw new RuntimeException('A store section belongs to a store.');

            return $this->tooManyRequests($exceeded, 'seller.store.show', ['page' => StorePageData::build($profile)]);
        }

        $moveStoreSection($section, $request->direction());

        return redirect()->route('seller.store.show')->with('status', 'Moved.');
    }
}
