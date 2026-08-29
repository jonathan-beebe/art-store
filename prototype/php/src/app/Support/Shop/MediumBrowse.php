<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Listing;

/**
 * The browse row's data with photo covers: every for-sale medium from
 * {@see MediumOptions}, each carrying its for-sale listing count and a
 * cover image — the most-favorited for-sale listing in that medium
 * (newest breaks the tie), so the picker refreshes as the catalog moves.
 */
final class MediumBrowse
{
    /**
     * @return list<array{value: string, label: string, count: int, coverUrl: string}>
     */
    public static function forStorefront(): array
    {
        return array_map(function (array $option): array {
            $inMedium = Listing::query()->forSale()->ofMediumAttribute($option['value']);

            $cover = (clone $inMedium)
                ->withCount('favorites')
                ->orderByDesc('favorites_count')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->firstOrFail();

            return $option + [
                'count' => (clone $inMedium)->count(),
                'coverUrl' => $cover->imageUrl(),
            ];
        }, MediumOptions::forStorefront());
    }
}
