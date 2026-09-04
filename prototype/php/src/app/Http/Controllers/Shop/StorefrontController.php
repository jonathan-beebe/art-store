<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Listing;
use App\Support\Shop\CategoryBrowse;
use App\Support\Shop\FeaturedSubject;
use App\Support\Shop\MediumBrowse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/` — an invitation to explore rather than the catalogue itself (DSGN-007):
 * the configured featured subject, the mediums and categories as browse
 * rows, and two curated listing sets (three newest, then the nine after
 * them) — never a paginated "everything", which `/medium/{medium}` and
 * `/browse/{categoryPath}` own. A legacy `q` or `medium` on this URL is a
 * bookmark or shared link from before those pages existed, so it is
 * redirected onto its new home rather than read here. A `medium` riding
 * alongside a `q` is dropped — the two never composed correctly (they
 * narrowed to unrelated result sets under one page) and neither new URL
 * carries the other's axis.
 */
final class StorefrontController extends ShopController
{
    private const JUST_LISTED_COUNT = 3;

    private const MORE_COUNT = 9;

    public function __invoke(Request $request): View|RedirectResponse
    {
        $term = $this->submitted($request, 'q');

        if ($term !== null) {
            return redirect()->route('shop.search', ['q' => $term]);
        }

        $medium = $this->submitted($request, 'medium');

        if ($medium !== null) {
            return redirect()->route('shop.medium', ['medium' => $medium]);
        }

        $newest = Listing::query()->forSale()
            ->with(['seller.storeProfile', 'images' => fn (Relation $images): Relation => $images->orderBy('position')])
            ->orderByDesc('created_at')->orderByDesc('id');

        return view('shop.home', [
            'featured' => FeaturedSubject::resolve(),
            'browse' => MediumBrowse::forStorefront(),
            'categories' => CategoryBrowse::forStorefront(),
            'justListed' => (clone $newest)->limit(self::JUST_LISTED_COUNT)->get(),
            'moreListings' => (clone $newest)->skip(self::JUST_LISTED_COUNT)->limit(self::MORE_COUNT)->get(),
        ]);
    }
}
