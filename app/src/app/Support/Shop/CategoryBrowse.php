<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

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
 *
 * The count and cover for every root are drawn from one pass over the
 * for-sale catalogue rather than a query per root: the catalogue is fetched
 * once, ordered by the same tie-break rule a single root's cover would use
 * (favorites desc, then created_at, then id), and each root's winner is the
 * first survivor of that order still in its subtree — so the query count
 * holds steady as the number of roots, or the size of the catalogue, grows.
 */
final class CategoryBrowse
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<array{category: Category, count: int, coverUrl: ?string}>
     */
    public static function forStorefront(): array
    {
        $categories = Category::query()->get(['id', 'parent_id', 'name', 'path', 'browsable']);

        /** @var list<Category> $roots */
        $roots = $categories
            ->filter(fn (Category $category): bool => $category->parent_id === null && $category->browsable)
            ->sortBy('name')
            ->values()
            ->all();

        if ($roots === []) {
            return [];
        }

        /** @var Collection<string, string> $pathById */
        $pathById = $categories->pluck('path', 'id');

        $candidates = Listing::query()
            ->forSale()
            ->select(['listings.id', 'listings.category_id', 'listings.created_at'])
            ->withCount('favorites')
            ->orderByDesc('favorites_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $countByRoot = [];
        $coverIdByRoot = [];

        foreach ($roots as $root) {
            $inSubtree = $candidates->filter(
                fn (Listing $listing): bool => $listing->category_id !== null
                    && str_starts_with($pathById->get($listing->category_id, ''), $root->path)
            );

            $countByRoot[$root->id] = $inSubtree->count();
            $cover = $inSubtree->first();

            if ($cover instanceof Listing) {
                $coverIdByRoot[$root->id] = $cover->id;
            }
        }

        $coversById = self::loadCovers(array_values($coverIdByRoot));

        return array_map(function (Category $root) use ($countByRoot, $coverIdByRoot, $coversById): array {
            $coverId = $coverIdByRoot[$root->id] ?? null;

            return [
                'category' => $root,
                'count' => $countByRoot[$root->id] ?? 0,
                'coverUrl' => $coverId === null ? null : $coversById->get($coverId)?->imageUrl(),
            ];
        }, $roots);
    }

    /**
     * @param  list<string>  $listingIds
     * @return Collection<string, Listing>
     */
    private static function loadCovers(array $listingIds): Collection
    {
        if ($listingIds === []) {
            /** @var Collection<string, Listing> $none */
            $none = collect();

            return $none;
        }

        /** @var Collection<string, Listing> $covers */
        $covers = Listing::query()
            ->whereIn('id', $listingIds)
            ->with(['images' => fn (Relation $images): Relation => $images->orderBy('position')])
            ->get()
            ->keyBy(fn (Listing $listing): string => $listing->id);

        return $covers;
    }
}
