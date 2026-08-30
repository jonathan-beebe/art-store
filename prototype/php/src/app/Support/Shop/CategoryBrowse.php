<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Category;
use App\Models\Listing;

/**
 * The home page's category row: every browsable root category, each carrying
 * its for-sale listing count across itself and its descendants — the same
 * set `/browse/{categoryPath}` lists. A hidden root, or one with no
 * browsable listings of its own, still appears with a count of zero; only
 * `browsable = false` removes it, mirroring the column's other read (the
 * browse page's own 404).
 */
final class CategoryBrowse
{
    /**
     * @return list<array{category: Category, count: int}>
     */
    public static function forStorefront(): array
    {
        /** @var list<Category> $roots */
        $roots = array_values(Category::query()
            ->whereNull('parent_id')
            ->where('browsable', true)
            ->orderBy('name')
            ->get()
            ->all());

        return array_map(fn (Category $category): array => [
            'category' => $category,
            'count' => Listing::query()->forSale()->ofCategoryPathPrefix($category->path)->count(),
        ], $roots);
    }
}
