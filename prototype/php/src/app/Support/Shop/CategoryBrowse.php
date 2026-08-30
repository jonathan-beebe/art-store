<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Category;
use App\Models\Listing;

/**
 * The home page's category row: every browsable root category, each carrying
 * its for-sale listing count across itself and its descendants — the same
 * set `/browse/{categoryPath}` lists — and a cover image: the most-favorited
 * for-sale listing anywhere in the category's subtree (newest breaks the
 * tie), the same rule {@see MediumBrowse} draws its own cover from. A hidden
 * root, or one with no browsable listings of its own, still appears with a
 * count of zero and `coverUrl: null` — unlike a medium, a category can be
 * genuinely empty, so the cover is the one nullable field here; only
 * `browsable = false` removes a root, mirroring the column's other read (the
 * browse page's own 404).
 */
final class CategoryBrowse
{
    /**
     * @return list<array{category: Category, count: int, coverUrl: ?string}>
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

        return array_map(function (Category $category): array {
            $inCategory = Listing::query()->forSale()->ofCategoryPathPrefix($category->path);

            $cover = (clone $inCategory)
                ->withCount('favorites')
                ->orderByDesc('favorites_count')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            return [
                'category' => $category,
                'count' => (clone $inCategory)->count(),
                'coverUrl' => $cover?->imageUrl(),
            ];
        }, $roots);
    }
}
