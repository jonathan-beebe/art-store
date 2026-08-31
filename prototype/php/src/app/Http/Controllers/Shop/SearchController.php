<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Shop\ListingSearch;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * `/search` — free text over the catalogue's titles, descriptions, and
 * Medium attribute labels. Search does not compose with `/medium` or
 * `/browse`: an absent or blank `q` runs no query at all, rather than
 * standing in for "every listing" — `/` itself no longer does that either
 * (DSGN-007); nowhere on the storefront does an empty filter mean "show
 * everything".
 */
final class SearchController extends ShopController
{
    private const LISTINGS_PER_PAGE = 12;

    public function __invoke(Request $request): View
    {
        $search = ListingSearch::fromInput($this->submitted($request, 'q'), null);

        return view('shop.search', [
            'search' => $search,
            'listings' => $search->hasTerm()
                ? $this->matching($search)->paginate(self::LISTINGS_PER_PAGE)->withQueryString()
                : null,
        ]);
    }

    /**
     * @return Builder<Listing>
     */
    private function matching(ListingSearch $search): Builder
    {
        return Listing::query()->forSale()
            ->with(['seller', 'images' => fn (Relation $images): Relation => $images->orderBy('position')])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->ofSearchTerm($search->likePattern());
    }
}
