<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Store\StoreViewCollapse;
use App\Models\Seller;
use App\Models\StoreProfile;
use App\Seller\Store\StoreAddressLookup;
use App\Seller\Store\StorefrontStorePageData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * `/s/{slug}` — a maker's own page. An address the store left behind
 * forwards permanently to the one it moved to while the redirect window is
 * open; an older one, an unknown one, and a hidden store all answer the
 * same 404, so a hidden store is never confirmed to exist. Its own seller
 * is the exception: they see the page with a banner saying buyers cannot.
 */
final class StoreController extends ShopController
{
    private const int MOVED_PERMANENTLY = 301;

    public function __invoke(string $slug, StoreAddressLookup $addresses, Analytics $analytics): View|RedirectResponse
    {
        $profile = $addresses->current($slug);

        if (! $profile instanceof StoreProfile) {
            $movedTo = $addresses->movedTo($slug, $this->now());

            abort_if($movedTo === null, 404);

            return redirect()->route('shop.store', ['slug' => $movedTo], self::MOVED_PERMANENTLY);
        }

        $isOwnStore = $this->isOwnStore($profile);

        abort_unless($profile->isPublished() || $isOwnStore, 404);

        if ($profile->isPublished()) {
            $this->recordView($profile, $analytics);
        }

        return view('shop.store', ['page' => StorefrontStorePageData::build($profile, $isOwnStore)]);
    }

    private function isOwnStore(StoreProfile $profile): bool
    {
        $seller = Auth::guard('seller')->user();

        return $seller instanceof Seller && $seller->id === $profile->seller_id;
    }

    /**
     * A store view is an event worth an id (docs/spec.md §4.1), so this is
     * where a first-time visitor's row gets minted.
     */
    private function recordView(StoreProfile $profile, Analytics $analytics): void
    {
        $visitor = $this->knownVisitor();
        $now = $this->now();

        $analytics->recordEvent(AnalyticsEvent::forStore(
            AnalyticsEventName::StoreView,
            $profile->id,
            $visitor->id,
            $now,
            StoreViewCollapse::dedupeKey($profile->id, $visitor->id, $now),
        ));
    }
}
