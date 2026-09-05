<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Listing;
use App\Shop\MediumBrowse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * One design-system specimen per request, rendered bare so the
 * design-system page can frame it in a phone-width iframe — inside the
 * frame, media queries and fixed positioning resolve to the frame's own
 * viewport, which is what makes the mobile presentations honest.
 */
final class DesignSystemSpecimenController extends ShopController
{
    private const SPECIMENS = ['browse-sheet', 'cover-rail', 'buy-bar', 'swipe-gallery'];

    public function __invoke(string $specimen): View
    {
        abort_unless(in_array($specimen, self::SPECIMENS, true), 404);

        return view('shop.design-system.specimens.'.$specimen, [
            'browse' => MediumBrowse::forStorefront(),
            'listings' => Listing::query()->forSale()
                ->with(['seller.storeProfile', 'images' => fn (Relation $query): Relation => $query->orderBy('position')])
                ->orderByDesc('created_at')->orderByDesc('id')->limit(4)->get(),
        ]);
    }
}
