<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * `/browse/{categoryPath}` — for-sale listings placed in one category or its
 * descendants. `$categoryPath` is one or two slug segments (the route's
 * `where` admits no more); the category itself has no slug column, so it is
 * matched by rebuilding the materialized `path` those segments came from.
 */
final class BrowseController extends ShopController
{
    private const LISTINGS_PER_PAGE = 12;

    public function __invoke(string $categoryPath): View
    {
        $category = Category::query()->where('path', '/'.trim($categoryPath, '/').'/')->first();

        abort_unless($category instanceof Category && $category->browsable, 404);

        return view('shop.browse', [
            'category' => $category,
            'children' => $category->children()->where('browsable', true)->orderBy('name')->get(),
            'listings' => Listing::query()->forSale()
                ->with(['seller.storeProfile', 'images' => fn (Relation $images): Relation => $images->orderBy('position')])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->ofCategoryPathPrefix($category->path)
                ->paginate(self::LISTINGS_PER_PAGE)->withQueryString(),
        ]);
    }
}
