<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Listing;
use App\Support\Shop\CategoryBrowse;
use App\Support\Shop\MediumBrowse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/` — every for-sale listing, plus the browse rows that lead to the
 * one-dimension-one-prefix pages: `/medium/{medium}` and
 * `/browse/{categoryPath}`. The home page itself no longer filters; a
 * legacy `q` or `medium` on this URL is a bookmark or shared link from
 * before those pages existed, so it is redirected onto its new home rather
 * than read here. A `medium` riding alongside a `q` is dropped — the two
 * never composed correctly (they narrowed to unrelated result sets under
 * one page) and neither new URL carries the other's axis.
 */
final class StorefrontController extends ShopController
{
    private const LISTINGS_PER_PAGE = 12;

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

        return view('shop.home', [
            'browse' => MediumBrowse::forStorefront(),
            'categories' => CategoryBrowse::forStorefront(),
            'listings' => Listing::query()->forSale()->with('seller')
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(self::LISTINGS_PER_PAGE)->withQueryString(),
        ]);
    }
}
