<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Listing;
use App\Shop\MediumBrowse;
use App\Shop\MediumOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * `/medium/{medium}` — for-sale listings carrying the given Medium
 * attribute. `$medium` is the lowercase value {@see MediumOptions} already
 * derives from the label, so a route match is a lookup in the same list the
 * browse row renders.
 */
final class MediumController extends ShopController
{
    private const LISTINGS_PER_PAGE = 12;

    public function __invoke(string $medium): View
    {
        $label = $this->label($medium);

        abort_unless($label !== null, 404);

        return view('shop.medium', [
            'medium' => $medium,
            'label' => $label,
            'browse' => MediumBrowse::forStorefront(),
            'listings' => Listing::query()->forSale()
                ->with(['seller.storeProfile', 'images' => fn (Relation $images): Relation => $images->orderBy('position')])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->ofMediumAttribute($medium)
                ->paginate(self::LISTINGS_PER_PAGE)->withQueryString(),
        ]);
    }

    private function label(string $medium): ?string
    {
        return collect(MediumOptions::forStorefront())->firstWhere('value', $medium)['label'] ?? null;
    }
}
